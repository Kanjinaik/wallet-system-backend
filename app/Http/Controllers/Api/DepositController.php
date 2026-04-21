<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\RetailerController;
use App\Models\CommissionTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\AdminSetting;
use App\Models\WalletLimit;
use App\Events\BalanceUpdated;
use App\Events\NewTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepositController extends Controller
{
    private const RETAILER_DEPOSIT_COMMISSION_PERCENT = 0.02;

    public function deposit(Request $request)
    {
        try {
            // Keep backward compatibility with existing frontend calls that send
            // custom method names while preserving the current deposit flow.
            $normalizedPaymentMethod = $request->input('payment_method');
            if (in_array($normalizedPaymentMethod, ['manual', 'test_add'], true)) {
                $request->merge(['payment_method' => 'bank_transfer']);
            }

            $request->validate([
                'amount' => 'required|numeric|min:1|max:100000',
                'wallet_id' => 'required|exists:wallets,id',
                'payment_method' => 'required|string|in:bank_transfer,upi,credit_card,debit_card'
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

            [$transaction, $netCredit, $commissionDetails] = DB::transaction(function () use ($user, $wallet, $request) {
                $lockedWallet = Wallet::lockForUpdate()->findOrFail($wallet->id);

                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'to_wallet_id' => $lockedWallet->id,
                    'type' => 'deposit',
                    'amount' => $request->amount,
                    'reference' => 'TXN' . strtoupper(uniqid()),
                    'description' => "Deposit via {$request->payment_method}",
                    'status' => 'completed',
                    'metadata' => [
                        'payment_method' => $request->payment_method,
                        'test_mode' => false,
                        'processed_at' => now(),
                    ],
                ]);

                [$netCredit, $commissionDetails] = $this->applyRetailerDepositCommission(
                    $user,
                    $transaction,
                    $lockedWallet,
                    (float) $request->amount
                );

                return [$transaction, $netCredit, $commissionDetails];
            });

            $wallet->refresh();

            RetailerController::notify(
                $user->id,
                'wallet_updated',
                'Wallet Updated',
                'Deposit credited to your wallet.',
                [
                    'wallet_id' => $wallet->id,
                    'amount' => (float) $netCredit,
                    'original_amount' => (float) $request->amount,
                    'commission' => $commissionDetails,
                    'new_balance' => (float) $wallet->balance,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Deposit processed successfully',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'new_balance' => $wallet->balance,
                    'amount' => $request->amount,
                    'net_credited' => $netCredit,
                    'commission' => $commissionDetails,
                    'wallet_name' => $wallet->name
                ]
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
                'message' => 'Deposit failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createOrder(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'wallet_id' => 'required|exists:wallets,id',
            'firstname' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'return_url' => 'nullable|url|max:500',
        ]);

        $user = $request->user();
        $wallet = $user->wallets()->findOrFail($request->wallet_id);

        if ($wallet->is_frozen) {
            return response()->json(['message' => 'Cannot deposit to frozen wallet'], 422);
        }

        try {
            $payuConfig = $this->getPayUConfig();
            if ($payuConfig['has_masked_placeholder']) {
                return response()->json([
                    'message' => 'PayU credentials are invalid. Save the real Merchant Key and Salt in Admin Settings, not masked characters.'
                ], 500);
            }

            if ($payuConfig['configured']) {
                $amount = $this->formatPayUAmount((float) $request->amount);
                $txnid = 'PAYU' . strtoupper(uniqid());
                $firstname = trim((string) ($request->input('firstname') ?: $user->name ?: 'Customer'));
                $email = trim((string) ($request->input('email') ?: $user->email ?: 'customer@example.com'));
                $phone = preg_replace('/\D+/', '', (string) ($request->input('phone') ?: $user->phone ?: '9999999999'));
                $productInfo = 'Wallet Deposit';
                $returnUrl = (string) $request->input('return_url', '');

                $formFields = [
                    'key' => $payuConfig['merchant_key'],
                    'txnid' => $txnid,
                    'amount' => $amount,
                    'productinfo' => $productInfo,
                    'firstname' => $firstname,
                    'email' => $email,
                    'phone' => $phone !== '' ? $phone : '9999999999',
                    'surl' => route('api.deposit.payu.callback'),
                    'furl' => route('api.deposit.payu.callback'),
                    'service_provider' => 'payu_paisa',
                    'udf1' => (string) $wallet->id,
                    'udf2' => (string) $user->id,
                    'udf3' => $returnUrl,
                    'udf4' => (string) $user->role,
                    'udf5' => 'wallet_deposit',
                ];
                $formFields['hash'] = $this->generatePayURequestHash($formFields, $payuConfig['merchant_salt']);

                return response()->json([
                    'gateway' => 'payu',
                    'payment_url' => $payuConfig['payment_url'],
                    'gateway_order_id' => $txnid,
                    'form_fields' => $formFields,
                    'amount' => (float) $request->amount,
                    'wallet_id' => (int) $wallet->id,
                    'test_mode' => $payuConfig['mode'] !== 'live',
                    'payu_mode' => $payuConfig['mode'],
                ]);
            }

            return response()->json([
                'message' => 'PayU Money credentials are not configured'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Deposit order creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        return response()->json([
            'message' => 'Direct deposit verification is not used for PayU Money. The gateway callback completes the deposit automatically.'
        ], 410);
    }

    public function webhook(Request $request)
    {
        return response()->json(['message' => 'Legacy deposit webhook removed'], 410);
    }

    public function handlePayUResponse(Request $request)
    {
        $params = $request->all();

        try {
            $payuConfig = $this->getPayUConfig();
            if ($payuConfig['has_masked_placeholder'] || !$payuConfig['configured']) {
                return redirect()->away($this->buildFrontendDepositRedirectUrl(null, [
                    'deposit_status' => 'failed',
                    'deposit_message' => 'PayU Money is not configured correctly.',
                ]));
            }

            $userId = (int) ($params['udf2'] ?? 0);
            $walletId = (int) ($params['udf1'] ?? 0);
            $user = $userId > 0 ? User::find($userId) : null;

            if (($params['key'] ?? '') !== $payuConfig['merchant_key']) {
                return redirect()->away($this->buildFrontendDepositRedirectUrl($user, [
                    'deposit_status' => 'failed',
                    'deposit_message' => 'PayU merchant key mismatch.',
                ]));
            }

            if (!$this->isValidPayUResponseHash($params, $payuConfig['merchant_salt'])) {
                return redirect()->away($this->buildFrontendDepositRedirectUrl($user, [
                    'deposit_status' => 'failed',
                    'deposit_message' => 'Invalid PayU response signature.',
                ]));
            }

            $status = strtolower((string) ($params['status'] ?? ''));
            if ($status !== 'success') {
                $failureMessage = trim((string) ($params['error_Message'] ?? $params['unmappedstatus'] ?? 'Payment failed'));

                return redirect()->away($this->buildFrontendDepositRedirectUrl($user, [
                    'deposit_status' => 'failed',
                    'deposit_message' => $failureMessage,
                    'wallet_id' => $walletId > 0 ? (string) $walletId : null,
                    'txnid' => (string) ($params['txnid'] ?? ''),
                ], (string) ($params['udf3'] ?? '')));
            }

            if (!$user || $walletId <= 0) {
                return redirect()->away($this->buildFrontendDepositRedirectUrl($user, [
                    'deposit_status' => 'failed',
                    'deposit_message' => 'Unable to map the PayU deposit to a wallet.',
                ], (string) ($params['udf3'] ?? '')));
            }

            $wallet = Wallet::where('id', $walletId)
                ->where('user_id', $user->id)
                ->first();

            if (!$wallet) {
                return redirect()->away($this->buildFrontendDepositRedirectUrl($user, [
                    'deposit_status' => 'failed',
                    'deposit_message' => 'Target wallet not found for this PayU deposit.',
                ], (string) ($params['udf3'] ?? '')));
            }

            $mihpayid = (string) ($params['mihpayid'] ?? '');
            $txnid = (string) ($params['txnid'] ?? '');
            $amount = round((float) ($params['amount'] ?? 0), 2);

            if ($mihpayid === '' || $txnid === '' || $amount < 1) {
                return redirect()->away($this->buildFrontendDepositRedirectUrl($user, [
                    'deposit_status' => 'failed',
                    'deposit_message' => 'Incomplete PayU response received.',
                ], (string) ($params['udf3'] ?? '')));
            }

            $alreadyProcessed = Transaction::where('type', 'deposit')
                ->where(function ($query) use ($mihpayid, $txnid) {
                    $query->whereJsonContains('metadata->payu_mihpayid', $mihpayid)
                        ->orWhereJsonContains('metadata->payu_txnid', $txnid);
                })
                ->exists();

            if ($alreadyProcessed) {
                return redirect()->away($this->buildFrontendDepositRedirectUrl($user, [
                    'deposit_status' => 'success',
                    'deposit_message' => 'Payment already processed.',
                    'wallet_id' => (string) $wallet->id,
                    'txnid' => $txnid,
                ], (string) ($params['udf3'] ?? '')));
            }

            [$transaction, $netCredit, $commissionDetails] = DB::transaction(function () use ($user, $wallet, $params, $amount, $txnid, $mihpayid) {
                $lockedWallet = Wallet::lockForUpdate()->findOrFail($wallet->id);

                $transaction = $user->transactions()->create([
                    'to_wallet_id' => $lockedWallet->id,
                    'type' => 'deposit',
                    'amount' => $amount,
                    'reference' => Transaction::generateReference(),
                    'description' => 'Deposit via PayU Money',
                    'status' => 'completed',
                    'metadata' => [
                        'test_mode' => false,
                        'payment_method' => 'payumoney',
                        'payu_txnid' => $txnid,
                        'payu_mihpayid' => $mihpayid,
                        'payu_status' => (string) ($params['status'] ?? ''),
                        'payu_mode' => (string) ($params['mode'] ?? ''),
                        'payu_bank_ref_num' => (string) ($params['bank_ref_num'] ?? ''),
                        'payu_addedon' => (string) ($params['addedon'] ?? ''),
                    ],
                ]);

                [$netCredit, $commissionDetails] = $this->applyRetailerDepositCommission(
                    $user,
                    $transaction,
                    $lockedWallet,
                    $amount
                );

                return [$transaction, $netCredit, $commissionDetails];
            });

            $wallet->refresh();

            RetailerController::notify(
                $user->id,
                'wallet_updated',
                'Wallet Updated',
                'Deposit credited to your wallet.',
                [
                    'wallet_id' => $wallet->id,
                    'amount' => (float) $netCredit,
                    'original_amount' => $amount,
                    'commission' => $commissionDetails,
                    'new_balance' => (float) $wallet->balance,
                ]
            );

            event(new BalanceUpdated($wallet->id, $wallet->balance, $user->id));
            event(new NewTransaction($transaction));

            WalletLimit::updateLimit($user->id, $amount, 'daily');
            WalletLimit::updateLimit($user->id, $amount, 'monthly');

            return redirect()->away($this->buildFrontendDepositRedirectUrl($user, [
                'deposit_status' => 'success',
                'deposit_message' => 'Deposit successful',
                'wallet_id' => (string) $wallet->id,
                'txnid' => $txnid,
            ], (string) ($params['udf3'] ?? '')));
        } catch (\Throwable $e) {
            Log::error('PayU deposit callback failed: ' . $e->getMessage(), [
                'payload' => $params,
            ]);

            $user = isset($user) ? $user : null;

            return redirect()->away($this->buildFrontendDepositRedirectUrl($user, [
                'deposit_status' => 'failed',
                'deposit_message' => 'Deposit failed: ' . $e->getMessage(),
            ], (string) ($params['udf3'] ?? '')));
        }
    }

    private function applyRetailerDepositCommission(User $user, Transaction $transaction, Wallet $retailerWallet, float $amount): array
    {
        $commissionRates = $this->getDepositCommissionRates();
        $baseCharge = $this->getRetailerDepositBaseCharge();

        $isRetailer = in_array($user->role, ['retailer', 'user'], true);
        if (!$isRetailer) {
            $retailerWallet->balance += $amount;
            $retailerWallet->save();

            return [$amount, [
                'admin_amount' => 0,
                'master_distributor_amount' => 0,
                'super_distributor_amount' => 0,
                'distributor_amount' => 0,
                'base_charge' => 0,
                'total_commission' => 0,
                'total_deduction' => 0,
                'commission_rate' => self::RETAILER_DEPOSIT_COMMISSION_PERCENT,
                'admin_rate' => $commissionRates['admin'],
                'master_distributor_rate' => $commissionRates['master_distributor'],
                'super_distributor_rate' => $commissionRates['super_distributor'],
                'distributor_rate' => $commissionRates['distributor'],
            ]];
        }

        $adminCommission = round($amount * ($commissionRates['admin'] / 100), 2);
        $masterCommission = round($amount * ($commissionRates['master_distributor'] / 100), 2);
        $superCommission = round($amount * ($commissionRates['super_distributor'] / 100), 2);
        $distributorCommission = round($amount * ($commissionRates['distributor'] / 100), 2);
        $totalCommission = round($adminCommission + $masterCommission + $superCommission + $distributorCommission, 2);
        $netCredit = round($amount - $totalCommission, 2);

        $distributorUser = null;
        $superDistributorUser = null;
        $masterDistributorUser = null;
        if ($user->distributor_id) {
            $distributorUser = User::where('id', $user->distributor_id)
                ->where('role', 'distributor')
                ->first();
        }
        if ($distributorUser && $distributorUser->distributor_id) {
            $superDistributorUser = User::where('id', $distributorUser->distributor_id)
                ->where('role', 'super_distributor')
                ->first();
        }
        if ($superDistributorUser && $superDistributorUser->distributor_id) {
            $masterDistributorUser = User::where('id', $superDistributorUser->distributor_id)
                ->where('role', 'master_distributor')
                ->first();
        }
        $adminUser = User::where('role', 'admin')->first();

        $creditedAdmin = $this->creditCommissionWallet(
            recipient: $adminUser,
            preferredWalletType: 'main',
            fallbackWalletType: 'sub',
            commissionType: 'admin',
            amount: $adminCommission,
            percentage: $commissionRates['admin'],
            transaction: $transaction,
            description: "Admin commission from {$user->name}'s deposit"
        );

        $creditedMaster = $this->creditCommissionWallet(
            recipient: $masterDistributorUser,
            preferredWalletType: 'sub',
            fallbackWalletType: 'main',
            commissionType: 'master_distributor',
            amount: $masterCommission,
            percentage: $commissionRates['master_distributor'],
            transaction: $transaction,
            description: "Master distributor commission from {$user->name}'s deposit"
        );

        $creditedSuper = $this->creditCommissionWallet(
            recipient: $superDistributorUser,
            preferredWalletType: 'sub',
            fallbackWalletType: 'main',
            commissionType: 'super_distributor',
            amount: $superCommission,
            percentage: $commissionRates['super_distributor'],
            transaction: $transaction,
            description: "Super distributor commission from {$user->name}'s deposit"
        );

        $creditedDistributor = $this->creditCommissionWallet(
            recipient: $distributorUser,
            preferredWalletType: 'sub',
            fallbackWalletType: 'main',
            commissionType: 'distributor',
            amount: $distributorCommission,
            percentage: $commissionRates['distributor'],
            transaction: $transaction,
            description: "Distributor commission from {$user->name}'s deposit"
        );

        $effectiveTotalCommission = round($creditedAdmin + $creditedMaster + $creditedSuper + $creditedDistributor, 2);
        $totalDeduction = round($baseCharge + $effectiveTotalCommission, 2);
        $effectiveNetCredit = round(max($amount - $totalDeduction, 0), 2);

        $retailerWallet->balance += $effectiveNetCredit;
        $retailerWallet->save();

        return [$effectiveNetCredit, [
            'admin_amount' => $creditedAdmin,
            'master_distributor_amount' => $creditedMaster,
            'super_distributor_amount' => $creditedSuper,
            'distributor_amount' => $creditedDistributor,
            'base_charge' => $baseCharge,
            'total_commission' => $effectiveTotalCommission,
            'total_deduction' => $totalDeduction,
            'commission_rate' => self::RETAILER_DEPOSIT_COMMISSION_PERCENT,
            'admin_rate' => $commissionRates['admin'],
            'master_distributor_rate' => $commissionRates['master_distributor'],
            'super_distributor_rate' => $commissionRates['super_distributor'],
            'distributor_rate' => $commissionRates['distributor'],
        ]];
    }

    private function getRetailerDepositBaseCharge(): float
    {
        return round((float) AdminSetting::getValue('sys_transaction_fee', '0'), 2);
    }

    private function getDepositCommissionRates(): array
    {
        return [
            'admin' => (float) AdminSetting::getValue('sys_deposit_commission_admin', (string) self::RETAILER_DEPOSIT_COMMISSION_PERCENT),
            'master_distributor' => (float) AdminSetting::getValue('sys_deposit_commission_master_distributor', (string) self::RETAILER_DEPOSIT_COMMISSION_PERCENT),
            'super_distributor' => (float) AdminSetting::getValue('sys_deposit_commission_super_distributor', (string) self::RETAILER_DEPOSIT_COMMISSION_PERCENT),
            'distributor' => (float) AdminSetting::getValue('sys_deposit_commission_distributor', (string) self::RETAILER_DEPOSIT_COMMISSION_PERCENT),
        ];
    }

    private function creditCommissionWallet(
        ?User $recipient,
        string $preferredWalletType,
        string $fallbackWalletType,
        string $commissionType,
        float $amount,
        float $percentage,
        Transaction $transaction,
        string $description
    ): float {
        if (!$recipient || $amount <= 0) {
            return 0;
        }

        $wallet = Wallet::lockForUpdate()
            ->where('user_id', $recipient->id)
            ->where('type', $preferredWalletType)
            ->first();

        if (!$wallet) {
            $wallet = Wallet::lockForUpdate()
                ->where('user_id', $recipient->id)
                ->where('type', $fallbackWalletType)
                ->first();
        }

        if (!$wallet) {
            return 0;
        }

        CommissionTransaction::create([
            'original_transaction_id' => $transaction->id,
            'user_id' => $recipient->id,
            'wallet_id' => $wallet->id,
            'commission_type' => $commissionType,
            'original_amount' => $transaction->amount,
            'commission_percentage' => $percentage,
            'commission_amount' => $amount,
            'reference' => CommissionTransaction::generateReference(),
            'description' => $description,
        ]);

        $wallet->balance += $amount;
        $wallet->save();

        RetailerController::notify(
            $recipient->id,
            'commission_credited',
            'Commission Credited',
            'Commission credited to your wallet.',
            [
                'amount' => $amount,
                'original_transaction_id' => $transaction->id,
            ]
        );

        return $amount;
    }

    private function getPayUConfig(): array
    {
        $merchantKey = trim((string) AdminSetting::getValue('sys_gateway_payu_key', ''));
        $merchantSalt = trim((string) AdminSetting::getValue('sys_gateway_payu_salt', ''));
        $mode = trim((string) AdminSetting::getValue('sys_gateway_payu_mode', 'test'));
        $hasMaskedPlaceholder = $this->isMaskedCredential($merchantKey) || $this->isMaskedCredential($merchantSalt);
        $normalizedMode = $mode === 'live' ? 'live' : 'test';

        return [
            'merchant_key' => $merchantKey,
            'merchant_salt' => $merchantSalt,
            'mode' => $normalizedMode,
            'payment_url' => $normalizedMode === 'live'
                ? 'https://secure.payu.in/_payment'
                : 'https://test.payu.in/_payment',
            'configured' => $merchantKey !== '' && $merchantSalt !== '' && !$hasMaskedPlaceholder,
            'has_masked_placeholder' => $hasMaskedPlaceholder,
        ];
    }

    private function generatePayURequestHash(array $fields, string $salt): string
    {
        $hashString = implode('|', [
            (string) ($fields['key'] ?? ''),
            (string) ($fields['txnid'] ?? ''),
            (string) ($fields['amount'] ?? ''),
            (string) ($fields['productinfo'] ?? ''),
            (string) ($fields['firstname'] ?? ''),
            (string) ($fields['email'] ?? ''),
            (string) ($fields['udf1'] ?? ''),
            (string) ($fields['udf2'] ?? ''),
            (string) ($fields['udf3'] ?? ''),
            (string) ($fields['udf4'] ?? ''),
            (string) ($fields['udf5'] ?? ''),
            '',
            '',
            '',
            '',
            '',
            $salt,
        ]);

        return strtolower(hash('sha512', $hashString));
    }

    private function isValidPayUResponseHash(array $params, string $salt): bool
    {
        $status = (string) ($params['status'] ?? '');
        $postedHash = strtolower((string) ($params['hash'] ?? ''));
        $additionalCharges = (string) ($params['additionalCharges'] ?? '');

        if ($status === '' || $postedHash === '') {
            return false;
        }

        $segments = [];
        if ($additionalCharges !== '') {
            $segments[] = $additionalCharges;
        }

        $segments = array_merge($segments, [
            $salt,
            $status,
            '',
            '',
            '',
            '',
            '',
            (string) ($params['udf5'] ?? ''),
            (string) ($params['udf4'] ?? ''),
            (string) ($params['udf3'] ?? ''),
            (string) ($params['udf2'] ?? ''),
            (string) ($params['udf1'] ?? ''),
            (string) ($params['email'] ?? ''),
            (string) ($params['firstname'] ?? ''),
            (string) ($params['productinfo'] ?? ''),
            (string) ($params['amount'] ?? ''),
            (string) ($params['txnid'] ?? ''),
            (string) ($params['key'] ?? ''),
        ]);

        $reverseHashString = implode('|', $segments);

        $expectedHash = strtolower(hash('sha512', $reverseHashString));

        return hash_equals($expectedHash, $postedHash);
    }

    private function isMaskedCredential(string $value): bool
    {
        return $value !== '' && preg_match('/^[*xX]+$/', $value) === 1;
    }

    private function formatPayUAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function buildFrontendDepositRedirectUrl(?User $user, array $params = [], string $preferredUrl = ''): string
    {
        $fallbackPath = match ($user?->role) {
            'master_distributor' => '/master-distributor',
            'super_distributor' => '/super-distributor',
            'distributor' => '/distributor',
            default => '/retailer',
        };

        $baseUrl = trim($preferredUrl) !== ''
            ? trim($preferredUrl)
            : rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost')), '/') . $fallbackPath;

        $query = array_filter($params, static fn ($value) => $value !== null && $value !== '');

        if ($query === []) {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query($query);
    }
}
