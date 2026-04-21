<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\RetailerController;
use App\Models\AdminSetting;
use App\Models\CommissionConfig;
use App\Models\Transaction;
use App\Models\CommissionTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawRequest;
use App\Models\WalletLimit;
use App\Services\CommissionSummaryService;
use App\Services\ErtitechPayoutService;
use App\Services\WithdrawalChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawController extends Controller
{
    private function normalizeMoney(float $amount): float
    {
        return round($amount, 2);
    }

    private function isCommissionOnlyWithdrawRole(User $user): bool
    {
        return in_array($user->role, ['master_distributor', 'super_distributor', 'distributor'], true);
    }

    private function getCommissionWithdrawAvailable(User $user): float
    {
        if (!$this->isCommissionOnlyWithdrawRole($user)) {
            return INF;
        }

        return $this->normalizeMoney(CommissionSummaryService::forUser($user->id)['available']);
    }

    private function ensureWithdrawLimits(User $user, float $maxPerTransaction): void
    {
        $defaultDailyLimit = 500000.00;
        $defaultMonthlyLimit = 5000000.00;
        $defaultPerTransactionLimit = max(200000.00, $maxPerTransaction);

        $limits = [
            'daily' => [
                'max_amount' => $defaultDailyLimit,
                'reset_date' => now()->toDateString(),
            ],
            'monthly' => [
                'max_amount' => $defaultMonthlyLimit,
                'reset_date' => now()->startOfMonth()->toDateString(),
            ],
            'per_transaction' => [
                'max_amount' => $defaultPerTransactionLimit,
                'reset_date' => null,
            ],
        ];

        foreach ($limits as $limitType => $defaults) {
            $limit = WalletLimit::where('user_id', $user->id)
                ->where('limit_type', $limitType)
                ->first();

            if (!$limit) {
                WalletLimit::create([
                    'user_id' => $user->id,
                    'limit_type' => $limitType,
                    'max_amount' => $defaults['max_amount'],
                    'transaction_count' => 0,
                    'total_amount' => 0,
                    'reset_date' => $defaults['reset_date'],
                ]);
                continue;
            }

            $nextMaxAmount = max((float) $limit->max_amount, (float) $defaults['max_amount']);
            $nextResetDate = $limit->reset_date ?: $defaults['reset_date'];

            if ((float) $limit->max_amount !== $nextMaxAmount || $limit->reset_date !== $nextResetDate) {
                $limit->update([
                    'max_amount' => $nextMaxAmount,
                    'reset_date' => $nextResetDate,
                ]);
            }
        }
    }

    public function requestOtp(Request $request)
    {
        $request->validate([
            'wallet_id' => 'required|exists:wallets,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        $wallet = $user->wallets()->findOrFail($request->wallet_id);
        if ((float) $wallet->balance < (float) $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance',
            ], 422);
        }
        if ($this->isCommissionOnlyWithdrawRole($user)) {
            $availableCommission = $this->normalizeMoney($this->getCommissionWithdrawAvailable($user));
            $requestedAmount = $this->normalizeMoney((float) $request->amount);
            if ($requestedAmount > $availableCommission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawals for this role are limited to available commission earnings.',
                ], 422);
            }
        }

        $otp = (string) random_int(100000, 999999);
        $user->withdraw_otp_code = $otp;
        $user->withdraw_otp_expires_at = now()->addMinutes(10);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'OTP generated successfully',
            'otp' => $this->shouldExposeDevelopmentSecrets() ? $otp : null,
            'expires_at' => optional($user->withdraw_otp_expires_at)->toDateTimeString(),
        ]);
    }

    public function withdraw(Request $request)
    {
        try {
            $request->validate([
                'wallet_id' => 'required|exists:wallets,id',
                'amount' => 'required|numeric|min:0.01',
                'bank_account' => 'required|string|max:20',
                'ifsc_code' => 'required|string|max:15',
                'account_holder_name' => 'required|string|max:255',
                'beneficiary_mobile' => 'nullable|string|max:20',
                'otp_code' => 'nullable|string|size:6',
            ]);

            // Get authenticated user
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $wallet = $user->wallets()->findOrFail($request->wallet_id);

            // OTP is optional for withdrawal. Existing generated OTP should not block a valid request.
            // Users may still generate OTP for their own flow, but withdraw does not enforce it.

            $minAmount = (float) AdminSetting::getValue('withdraw_min_amount', 100);
            $maxAmount = (float) AdminSetting::getValue('withdraw_max_per_tx', 500000);
            $this->ensureWithdrawLimits($user, $maxAmount);

            if ($request->amount < $minAmount) {
                return response()->json(['message' => "Minimum withdrawal amount is {$minAmount}"], 422);
            }
            if ($maxAmount > 0 && $request->amount > $maxAmount) {
                return response()->json(['message' => "Maximum withdrawal per transaction is {$maxAmount}"], 422);
            }

            // Allow withdrawals from any wallet (frozen or unfrozen)
            // Only check balance and limits

        $requestedAmount = $this->normalizeMoney((float) $request->amount);
        $feeAmount = $this->normalizeMoney(WithdrawalChargeService::calculateRetailerWithdrawalCharge($requestedAmount, (string) $user->role));
        $totalDebitAmount = $this->normalizeMoney($requestedAmount + $feeAmount);

        if ($this->isCommissionOnlyWithdrawRole($user)) {
            $availableCommission = $this->normalizeMoney($this->getCommissionWithdrawAvailable($user));
            if ($totalDebitAmount > $availableCommission) {
                return response()->json(['message' => 'Withdrawals for this role are limited to available commission earnings.'], 422);
            }
        }

        if ((float) $wallet->balance < $totalDebitAmount) {
            return response()->json(['message' => 'Insufficient balance'], 422);
        }

        // Check limits
        if (!WalletLimit::checkLimit($user->id, $request->amount, 'per_transaction')) {
            return response()->json(['message' => 'Per-transaction limit exceeded'], 422);
        }
        if (!WalletLimit::checkLimit($user->id, $request->amount, 'daily')) {
            return response()->json(['message' => 'Daily limit exceeded'], 422);
        }
        if (!WalletLimit::checkLimit($user->id, $request->amount, 'monthly')) {
            return response()->json(['message' => 'Monthly limit exceeded'], 422);
        }

        return DB::transaction(function () use ($request, $wallet, $user) {
            $highValueEkycThreshold = 100000.00;
            $requiresEkycApproval = (float) $request->amount >= $highValueEkycThreshold;
            $approvalMode = strtolower((string) AdminSetting::getValue('withdraw_approval_mode', 'auto'));
            $requiresManualApproval = $approvalMode === 'manual';

            // Apply retailer withdrawal charges by slab (not commission).
            $withdrawalAmount = (float) $request->amount;
            $feeAmount = WithdrawalChargeService::calculateRetailerWithdrawalCharge($withdrawalAmount, (string) $user->role);
            // Customer receives full requested withdrawal amount.
            // Charge is debited separately from wallet balance.
            $netAmount = $withdrawalAmount;

            $totalDebit = $withdrawalAmount + $feeAmount;

            $withdrawRequest = WithdrawRequest::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'amount' => $request->amount,
                'net_amount' => (float) $request->amount,
                'status' => ($requiresEkycApproval || $requiresManualApproval) ? 'pending' : 'approved',
                'metadata' => [
                    'bank_account' => $request->bank_account,
                    'ifsc_code' => $request->ifsc_code,
                    'account_holder_name' => $request->account_holder_name,
                    'beneficiary_mobile' => $request->beneficiary_mobile,
                    'requires_ekyc_approval' => $requiresEkycApproval,
                    'requires_manual_approval' => $requiresManualApproval,
                    'withdraw_approval_mode' => $approvalMode,
                    'ekyc_threshold' => $highValueEkycThreshold,
                    'withdrawal_fee' => $feeAmount,
                    'net_payout_to_customer' => $netAmount,
                    'debited_amount' => $totalDebit,
                ],
            ]);

            $adminUserId = User::where('role', 'admin')->value('id');
            if ($adminUserId) {
                RetailerController::notify(
                    $adminUserId,
                    'withdraw_request_created',
                    'New withdraw request',
                    $user->name . ' submitted a withdraw request of Rs ' . number_format((float) $request->amount, 2),
                    [
                        'withdraw_request_id' => $withdrawRequest->id,
                        'user_id' => $user->id,
                        'amount' => (float) $request->amount,
                        'status' => $withdrawRequest->status,
                    ]
                );
            }

            if ($requiresEkycApproval || $requiresManualApproval) {
                $pendingMessage = $requiresEkycApproval
                    ? 'Withdrawal request submitted and pending eKYC approval.'
                    : 'Withdrawal request submitted and pending admin approval.';

                RetailerController::notify(
                    $user->id,
                    'withdraw_requested',
                    'Withdraw Requested',
                    $pendingMessage,
                    ['withdraw_request_id' => $withdrawRequest->id, 'amount' => (float) $request->amount]
                );

                $user->withdraw_otp_code = null;
                $user->withdraw_otp_expires_at = null;
                $user->save();

                return response()->json([
                    'success' => true,
                    'message' => $pendingMessage,
                    'withdraw_request_id' => $withdrawRequest->id,
                    'withdrawal_amount' => (float) $request->amount,
                    'debited_amount' => 0,
                    'original_amount' => $request->amount,
                ]);
            }

            $processed = $this->processApprovedRequest($withdrawRequest);
            $processingState = (string) ($processed['processing_state'] ?? 'completed');
            $responseMessage = $processingState === 'completed'
                ? 'Withdrawal successful'
                : 'Withdrawal initiated successfully and is pending bank confirmation.';

            RetailerController::notify(
                $user->id,
                'wallet_updated',
                'Wallet Updated',
                'Your wallet balance was updated after withdrawal.',
                [
                    'wallet_id' => $processed['wallet']->id,
                    'new_balance' => (float) $processed['wallet']->balance,
                ]
            );

            $user->withdraw_otp_code = null;
            $user->withdraw_otp_expires_at = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => $responseMessage,
                'transaction_id' => $processed['transaction']->id,
                'withdraw_request_id' => $withdrawRequest->id,
                'withdrawal_amount' => (float) $request->amount,
                'debited_amount' => (float) $request->amount + $feeAmount,
                'original_amount' => $request->amount,
                'processing_state' => $processingState,
                'transaction_status' => $processed['transaction']->status,
                'withdraw_request_status' => $processed['withdraw_request']->status,
            ]);
        });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function calculateCommission(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:100',
                'user_role' => 'required|in:admin,master_distributor,super_distributor,distributor,retailer',
            ]);

            $commissionCalculation = [
                'original_amount' => (float) $request->amount,
                'admin_commission_percentage' => 0,
                'distributor_commission_percentage' => 0,
                'admin_commission_amount' => 0,
                'distributor_commission_amount' => 0,
                'total_commission' => 0,
                'net_amount' => (float) $request->amount,
            ];

            return response()->json([
                'success' => true,
                'commission_details' => $commissionCalculation,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Commission calculation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function withdrawalHistory(Request $request)
    {
        $withdrawals = $request->user()->transactions()
            ->where('type', 'withdraw')
            ->with(['fromWallet'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($withdrawals);
    }

    private function processApprovedRequest(WithdrawRequest $withdrawRequest): array
    {
        $user = $withdrawRequest->user()->firstOrFail();
        $wallet = $withdrawRequest->wallet()->lockForUpdate()->firstOrFail();
        $payoutService = app(ErtitechPayoutService::class);

        // Get fee and net payout from metadata (calculated at withdrawal request time)
        if (isset($withdrawRequest->metadata['withdrawal_fee'])) {
            $feeAmount = (float) $withdrawRequest->metadata['withdrawal_fee'];
        } else {
            // Fallback calculation if fee not in metadata
            $withdrawalAmount = (float) $withdrawRequest->amount;
            $feeAmount = WithdrawalChargeService::calculateRetailerWithdrawalCharge($withdrawalAmount, (string) $user->role);
        }
        $netPayoutAmount = (float) $withdrawRequest->amount;
        $totalDebitAmount = (float) $withdrawRequest->amount + $feeAmount;

        if ((float) $wallet->balance < $totalDebitAmount) {
            throw new \RuntimeException('Insufficient balance during withdrawal processing.');
        }

        $payoutMeta = $payoutService->createPayout($withdrawRequest, $user, $netPayoutAmount);
        $processingState = $payoutService->getPayoutProcessingState($payoutMeta);

        if ($processingState === 'failed') {
            throw new \RuntimeException($payoutService->getFailureMessage($payoutMeta));
        }

        $transactionStatus = $processingState === 'completed' ? 'completed' : 'pending';
        $withdrawRequestStatus = $processingState === 'completed' ? 'processed' : 'pending';
        $notifyType = $processingState === 'completed' ? 'withdraw_processed' : 'withdraw_requested';
        $notifyTitle = $processingState === 'completed' ? 'Withdraw Approved' : 'Withdraw Initiated';
        $notifyMessage = $processingState === 'completed'
            ? 'Your withdrawal has been approved and processed successfully.'
            : 'Your withdrawal has been sent to the payout gateway and is pending bank confirmation.';

        $transaction = $user->transactions()->create([
            'from_wallet_id' => $wallet->id,
            'type' => 'withdraw',
            'amount' => $withdrawRequest->amount,
            'reference' => Transaction::generateReference(),
            'description' => 'Bank withdrawal',
            'status' => $transactionStatus,
            'metadata' => [
                'withdraw_request_id' => $withdrawRequest->id,
                'bank_account' => $withdrawRequest->metadata['bank_account'] ?? null,
                'ifsc_code' => $withdrawRequest->metadata['ifsc_code'] ?? null,
                'account_holder_name' => $withdrawRequest->metadata['account_holder_name'] ?? null,
                'beneficiary_mobile' => $withdrawRequest->metadata['beneficiary_mobile'] ?? null,
                'processing_time' => '24-48 hours',
                'original_amount' => $withdrawRequest->amount,
                'debited_amount' => $totalDebitAmount,
                'withdrawal_fee' => $feeAmount,
                'net_payout_amount' => $netPayoutAmount,
                'payout' => $payoutMeta,
            ]
        ]);

        // Deduct withdrawal amount + charge from retailer wallet
        $wallet->balance -= $totalDebitAmount;
        $wallet->save();

        // Add fee directly to admin main wallet (profit holding - NOT commission transaction).
        // This must succeed in the same transaction to keep balances exact.
        if ($feeAmount > 0) {
            $adminUser = User::where('role', 'admin')->lockForUpdate()->first();
            if (!$adminUser) {
                throw new \RuntimeException('Admin user not found for fee credit.');
            }

            $adminMainWallet = Wallet::lockForUpdate()
                ->where('user_id', $adminUser->id)
                ->where('type', 'main')
                ->first();

            if (!$adminMainWallet) {
                throw new \RuntimeException('Admin main wallet not found for fee credit.');
            }

            $adminMainWallet->balance = (float) $adminMainWallet->balance + (float) $feeAmount;
            $adminMainWallet->save();
        }

        WalletLimit::updateLimit($user->id, (float) $withdrawRequest->amount, 'daily');
        WalletLimit::updateLimit($user->id, (float) $withdrawRequest->amount, 'monthly');

        $withdrawRequest->status = $withdrawRequestStatus;
        $withdrawRequest->metadata = array_merge($withdrawRequest->metadata ?? [], [
            'payout' => $payoutMeta,
            'processing_state' => $processingState,
        ]);
        $withdrawRequest->reviewed_at = now();
        $withdrawRequest->save();

        RetailerController::notify(
            $user->id,
            $notifyType,
            $notifyTitle,
            $notifyMessage,
            [
                'withdraw_request_id' => $withdrawRequest->id,
                'transaction_id' => $transaction->id,
                'amount' => (float) $withdrawRequest->amount,
            ]
        );
        RetailerController::notify(
            $user->id,
            'wallet_updated',
            'Wallet Updated',
            'Your wallet balance was updated after withdrawal.',
            [
                'wallet_id' => $wallet->id,
                'new_balance' => (float) $wallet->balance,
            ]
        );

        return [
            'transaction' => $transaction,
            'wallet' => $wallet,
            'withdraw_request' => $withdrawRequest,
            'processing_state' => $processingState,
        ];
    }

    private function shouldExposeDevelopmentSecrets(): bool
    {
        return app()->environment('local') || (bool) config('app.debug');
    }

}
