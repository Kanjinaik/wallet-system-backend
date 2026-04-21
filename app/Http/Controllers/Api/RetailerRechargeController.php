<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\BillAvenueBbpsService;
use App\Services\RetailerRechargeStatusService;
use App\Services\SmsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RetailerRechargeController extends Controller
{
    public function pay(Request $request, BillAvenueBbpsService $bbps, RetailerRechargeStatusService $statusService, SmsNotificationService $smsNotifications)
    {
        $payload = $request->validate([
            'service' => 'required|string|in:prepaid-postpaid',
            'provider' => 'required|string|max:255',
            'payment_type' => 'required|string|in:prepaid',
            'mobile' => 'nullable|string|max:20',
            'customer_mobile' => 'nullable|string|max:20',
            'circle' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'board' => 'nullable|string|max:255',
            'subscriber_id' => 'nullable|string|max:255',
            'service_number' => 'nullable|string|max:255',
            'customer_id' => 'nullable|string|max:255',
            'account_id' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'card_number' => 'nullable|string|max:255',
            'student_id' => 'nullable|string|max:255',
            'policy_number' => 'nullable|string|max:255',
            'loan_account_number' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:1|max:50000',
        ]);

        $user = $request->user();
        $wallet = $user->wallets()->where('type', 'main')->first() ?: $user->wallets()->first();
        if (!$wallet) {
            return response()->json(['message' => 'Retailer wallet not found.'], 422);
        }
        if ($wallet->is_frozen) {
            return response()->json(['message' => 'Wallet is frozen.'], 422);
        }

        $amount = round((float) $payload['amount'], 2);
        if ((float) $wallet->balance < $amount) {
            return response()->json(['message' => 'Insufficient wallet balance for recharge.'], 422);
        }

        $reference = Transaction::generateReference();

        $transaction = DB::transaction(function () use ($user, $wallet, $amount, $payload, $reference) {
            $wallet = $wallet->fresh();
            $wallet->balance = round((float) $wallet->balance - $amount, 2);
            $wallet->save();

            return Transaction::create([
                'user_id' => $user->id,
                'from_wallet_id' => $wallet->id,
                'to_wallet_id' => null,
                'type' => 'recharge',
                'amount' => $amount,
                'reference' => $reference,
                'description' => $this->buildDescription($payload),
                'status' => 'pending',
                'metadata' => [
                    'wallet_debited' => true,
                    'wallet_refunded' => false,
                    'service' => $payload['service'],
                    'provider' => $payload['provider'],
                    'payment_type' => $payload['payment_type'] ?? null,
                    'request' => $payload,
                ],
            ]);
        });

        try {
            $providerResult = $bbps->submitRecharge(array_merge($payload, [
                'reference' => $reference,
                'customer_mobile' => $payload['customer_mobile'] ?? $payload['mobile'] ?? $user->phone,
            ]), $user, $request);

            $transaction->metadata = array_merge($transaction->metadata ?? [], [
                'provider' => $providerResult['mode'] ?? 'payu_nbc',
                'provider_request_id' => $providerResult['request_id'] ?? null,
                'provider_txn_ref_id' => $providerResult['txn_ref_id'] ?? null,
                'provider_approval_ref_number' => $providerResult['approval_ref_number'] ?? null,
                'provider_biller_id' => $providerResult['biller_id'] ?? null,
                'provider_result' => $providerResult,
                'ccf1' => $providerResult['ccf1'] ?? 0,
            ]);
            $transaction->save();
            $transaction = $statusService->applyProviderPayload(
                $transaction,
                $providerResult['payment'] ?? $providerResult,
                'provider_payment_response'
            );

            if ($transaction->status === 'completed') {
                RetailerController::notify(
                    $user->id,
                    'recharge_success',
                    'Recharge Successful',
                    'Mobile recharge of Rs. ' . number_format((float) $amount, 2, '.', '') . ' for ' . ($payload['provider'] ?? 'operator') . ' was completed successfully.',
                    [
                        'transaction_id' => $transaction->id,
                        'reference' => $transaction->reference,
                        'provider_reference' => $providerResult['txn_ref_id'] ?? null,
                    ]
                );
                $smsNotifications->sendRechargeStatus(
                    (string) ($payload['customer_mobile'] ?? $payload['mobile'] ?? $user->phone ?? ''),
                    'success',
                    (string) ($payload['provider'] ?? ''),
                    (float) $amount,
                    (string) $transaction->reference
                );
            } elseif ($transaction->status === 'pending') {
                RetailerController::notify(
                    $user->id,
                    'recharge_pending',
                    'Recharge Pending',
                    'Mobile recharge of Rs. ' . number_format((float) $amount, 2, '.', '') . ' for ' . ($payload['provider'] ?? 'operator') . ' is pending provider confirmation.',
                    [
                        'transaction_id' => $transaction->id,
                        'reference' => $transaction->reference,
                        'provider_reference' => $providerResult['txn_ref_id'] ?? null,
                    ]
                );
                $smsNotifications->sendRechargeStatus(
                    (string) ($payload['customer_mobile'] ?? $payload['mobile'] ?? $user->phone ?? ''),
                    'pending',
                    (string) ($payload['provider'] ?? ''),
                    (float) $amount,
                    (string) $transaction->reference
                );
            }

            return response()->json([
                'message' => ($providerResult['mode'] ?? '') === 'payu_manual'
                    ? 'Recharge request queued for manual processing.'
                    : ($transaction->status === 'completed'
                        ? 'Recharge completed successfully.'
                        : ($transaction->status === 'pending'
                            ? 'Recharge submitted and is pending provider confirmation.'
                            : 'Recharge failed and wallet amount has been refunded.')),
                'transaction' => $transaction->fresh(),
                'provider_status' => $transaction->status,
                'provider_reference' => $providerResult['txn_ref_id'] ?? null,
                'request_id' => $providerResult['request_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $transaction->metadata = array_merge($transaction->metadata ?? [], [
                'provider_error' => $e->getMessage(),
            ]);
            $transaction->status = 'failed';
            $transaction->save();

            $statusService->refundWalletForFailure($transaction);

            RetailerController::notify(
                $user->id,
                'recharge_failed',
                'Recharge Failed',
                'Mobile recharge of Rs. ' . number_format((float) $amount, 2, '.', '') . ' for ' . ($payload['provider'] ?? 'operator') . ' failed. Wallet amount has been refunded.',
                [
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'provider_error' => $e->getMessage(),
                ]
            );
            $smsNotifications->sendRechargeStatus(
                (string) ($payload['customer_mobile'] ?? $payload['mobile'] ?? $user->phone ?? ''),
                'failed',
                (string) ($payload['provider'] ?? ''),
                (float) $amount,
                (string) $transaction->reference
            );

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function syncStatus(Request $request, BillAvenueBbpsService $bbps, RetailerRechargeStatusService $statusService, int $id)
    {
        $transaction = $request->user()->transactions()->findOrFail($id);
        $providerRequestId = (string) ($transaction->metadata['provider_request_id'] ?? '');
        if ($providerRequestId === '') {
            return response()->json(['message' => 'Provider request id not found for this transaction.'], 422);
        }

        $statusResponse = $bbps->syncStatusByRequestId($providerRequestId);
        $transaction = $statusService->applyProviderPayload($transaction, $statusResponse, 'status_sync_response');

        return response()->json([
            'message' => 'Recharge status synchronized successfully.',
            'transaction' => $transaction,
        ]);
    }

    public function pushStatus(Request $request, BillAvenueBbpsService $bbps, RetailerRechargeStatusService $statusService)
    {
        $payload = $bbps->decodeIncomingPayload($request->getContent(), $request->all());
        if ($payload === []) {
            return response()->json(['message' => 'Unable to decode recharge push notification payload.'], 422);
        }

        $transaction = $statusService->locateTransaction($payload);
        if (!$transaction) {
            return response()->json(['message' => 'Recharge transaction not found for push notification.'], 202);
        }

        $transaction = $statusService->applyProviderPayload($transaction, $payload, 'provider_push_response');

        return response()->json([
            'message' => 'Recharge push notification processed successfully.',
            'transaction_id' => $transaction->id,
            'status' => $transaction->status,
        ]);
    }

    private function buildDescription(array $payload): string
    {
        $serviceLabel = $payload['service'] === 'prepaid-postpaid'
            ? (($payload['payment_type'] ?? 'prepaid') === 'postpaid' ? 'Postpaid Bill' : 'Mobile Recharge')
            : ucwords(str_replace('-', ' ', (string) $payload['service']));

        return trim($serviceLabel . ' for ' . ($payload['provider'] ?? ''));
    }
}
