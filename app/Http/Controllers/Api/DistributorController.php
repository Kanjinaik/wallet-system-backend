<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\RetailerController;
use App\Models\CommissionConfig;
use App\Models\CommissionOverride;
use App\Models\CommissionTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLimit;
use App\Models\WithdrawRequest;
use App\Services\CommissionSummaryService;
use App\Services\WithdrawalChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DistributorController extends Controller
{
    public function dashboard(Request $request)
    {
        $distributor = $request->user();

        $retailers = User::where('role', 'retailer')
            ->where('distributor_id', $distributor->id)
            ->with('wallets')
            ->orderBy('created_at', 'desc')
            ->get();

        $walletBalance = $distributor->wallets()->sum('balance');
        $commissionSummary = CommissionSummaryService::forUser($distributor->id);
        $performance = $this->buildPerformanceData($distributor->id);

        return response()->json([
            'wallet_balance' => (float) $walletBalance,
            'commission_earned' => $commissionSummary['earned'],
            'commission_available' => $commissionSummary['available'],
            'total_retailers' => $retailers->count(),
            'retailer_withdraw_summary' => $performance['retailer_withdraw_summary'],
            'weekly_chart' => $performance['weekly_chart'],
            'monthly_chart' => $performance['monthly_chart'],
            'bonus' => $performance['bonus'],
            'distributor_profile' => [
                'id' => $distributor->id,
                'name' => $distributor->name,
                'email' => $distributor->email,
                'phone' => $distributor->phone,
                'date_of_birth' => $distributor->date_of_birth,
                'is_active' => (bool) $distributor->is_active,
            ],
            'retailers' => $retailers->map(function ($retailer) {
                return [
                    'id' => $retailer->id,
                    'name' => $retailer->name,
                    'email' => $retailer->email,
                    'phone' => $retailer->phone,
                    'date_of_birth' => $retailer->date_of_birth,
                    'is_active' => (bool) $retailer->is_active,
                    'balance' => (float) $retailer->wallets->sum('balance'),
                    'commission_override' => $retailer->commissionOverride ? [
                        'id' => $retailer->commissionOverride->id,
                        'admin_commission' => (float) $retailer->commissionOverride->admin_commission,
                        'distributor_commission' => (float) $retailer->commissionOverride->distributor_commission,
                        'is_active' => (bool) $retailer->commissionOverride->is_active,
                    ] : null,
                ];
            })->values(),
        ]);
    }

    public function performance(Request $request)
    {
        $distributor = $request->user();
        return response()->json($this->buildPerformanceData($distributor->id));
    }

    public function retailers(Request $request)
    {
        $retailers = User::where('role', 'retailer')
            ->where('distributor_id', $request->user()->id)
            ->with(['wallets', 'commissionOverride'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($retailer) {
                return [
                    'id' => $retailer->id,
                    'name' => $retailer->name,
                    'email' => $retailer->email,
                    'phone' => $retailer->phone,
                    'date_of_birth' => $retailer->date_of_birth,
                    'is_active' => (bool) $retailer->is_active,
                    'balance' => (float) $retailer->wallets->sum('balance'),
                    'commission_override' => $retailer->commissionOverride ? [
                        'id' => $retailer->commissionOverride->id,
                        'admin_commission' => (float) $retailer->commissionOverride->admin_commission,
                        'distributor_commission' => (float) $retailer->commissionOverride->distributor_commission,
                        'is_active' => (bool) $retailer->commissionOverride->is_active,
                    ] : null,
                ];
            })->values();

        return response()->json($retailers);
    }

    public function createRetailer(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'date_of_birth' => 'required|date|before:today',
            'phone' => 'required|string|digits:10',
            'alternate_mobile' => 'nullable|string|digits:10',
            'business_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'kyc_document_type' => 'required|string|max:32',
            'kyc_id_number' => 'required|string|size:12',
            'pan_number' => 'required|string|size:10',
            'profile_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'address_proof_front' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'address_proof_back' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'pan_proof_front' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'pan_proof_back' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        $distributor = $request->user();

        $profilePhotoPath = $request->hasFile('profile_photo')
            ? $request->file('profile_photo')->store('users/profile-photo', 'public')
            : User::generateDefaultProfilePhotoPath($payload['name']);
        $panProofFrontPath = $request->hasFile('pan_proof_front')
            ? $request->file('pan_proof_front')->store('users/pan-proof/front', 'public')
            : null;
        $panProofBackPath = $request->hasFile('pan_proof_back')
            ? $request->file('pan_proof_back')->store('users/pan-proof/back', 'public')
            : null;
        $addressProofFrontPath = $request->hasFile('address_proof_front')
            ? $request->file('address_proof_front')->store('users/address-proof/front', 'public')
            : null;
        $addressProofBackPath = $request->hasFile('address_proof_back')
            ? $request->file('address_proof_back')->store('users/address-proof/back', 'public')
            : null;

        $retailer = User::create([
            'name' => $payload['name'],
            'last_name' => $payload['last_name'] ?? null,
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
            'plain_password' => $payload['password'],
            'phone' => $payload['phone'],
            'alternate_mobile' => $payload['alternate_mobile'] ?? null,
            'business_name' => $payload['business_name'] ?? null,
            'address' => $payload['address'] ?? null,
            'city' => $payload['city'] ?? null,
            'state' => $payload['state'] ?? null,
            'date_of_birth' => $payload['date_of_birth'],
            'profile_photo_path' => $profilePhotoPath,
            'kyc_id_number' => $payload['kyc_id_number'],
            'pan_number' => strtoupper($payload['pan_number']),
            'kyc_document_type' => $payload['kyc_document_type'],
            'kyc_photo_path' => $panProofFrontPath,
            'pan_proof_front_path' => $panProofFrontPath,
            'pan_proof_back_path' => $panProofBackPath,
            'address_proof_front_path' => $addressProofFrontPath,
            'address_proof_back_path' => $addressProofBackPath,
            'kyc_document_path' => $panProofFrontPath,
            'role' => 'retailer',
            'distributor_id' => $distributor->id,
            'is_active' => true,
        ]);

        $retailer->wallets()->create([
            'name' => 'Retailer Wallet',
            'type' => 'sub',
            'balance' => 0,
        ]);

        $retailer->walletLimits()->createMany([
            [
                'limit_type' => 'daily',
                'max_amount' => 500000,
                'reset_date' => now()->toDateString(),
            ],
            [
                'limit_type' => 'monthly',
                'max_amount' => 5000000,
                'reset_date' => now()->startOfMonth()->toDateString(),
            ],
            [
                'limit_type' => 'per_transaction',
                'max_amount' => 500000,
            ],
        ]);

        return response()->json([
            'message' => 'Retailer created successfully',
            'retailer' => [
                'id' => $retailer->id,
                'name' => $retailer->name,
                'email' => $retailer->email,
                'phone' => $retailer->phone,
                'date_of_birth' => $retailer->date_of_birth,
                'is_active' => (bool) $retailer->is_active,
                'balance' => 0,
            ],
        ], 201);
    }

    public function updateRetailer(Request $request, int $id)
    {
        $payload = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'distributor_commission' => 'nullable|numeric|min:0|max:100',
        ]);

        $distributor = $request->user();
        $retailer = $this->findOwnedRetailer($distributor->id, $id);

        if (array_key_exists('name', $payload)) {
            $retailer->name = $payload['name'];
        }
        if (array_key_exists('phone', $payload)) {
            $retailer->phone = $payload['phone'];
        }
        if (array_key_exists('date_of_birth', $payload)) {
            $retailer->date_of_birth = $payload['date_of_birth'];
        }
        $retailer->save();

        if (array_key_exists('distributor_commission', $payload)) {
            $default = CommissionConfig::getActiveConfig('retailer');
            $adminCommission = $default ? (float) $default->admin_commission : 0;

            CommissionOverride::updateOrCreate(
                ['user_id' => $retailer->id],
                [
                    'admin_commission' => $adminCommission,
                    'distributor_commission' => (float) $payload['distributor_commission'],
                    'is_active' => true,
                ]
            );
        }

        return response()->json([
            'message' => 'Retailer updated successfully',
        ]);
    }

    public function toggleRetailer(Request $request, int $id)
    {
        $distributor = $request->user();
        $retailer = $this->findOwnedRetailer($distributor->id, $id);
        $retailer->is_active = !$retailer->is_active;
        $retailer->save();

        return response()->json([
            'message' => 'Retailer ' . ($retailer->is_active ? 'activated' : 'deactivated') . ' successfully',
            'is_active' => (bool) $retailer->is_active,
        ]);
    }

    public function retailerTransactions(Request $request, int $id)
    {
        $distributor = $request->user();
        $retailer = $this->findOwnedRetailer($distributor->id, $id);

        $walletTransactions = $retailer->transactions()
            ->with(['fromWallet', 'toWallet'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $commissionTransactions = CommissionTransaction::where('user_id', $retailer->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'retailer' => [
                'id' => $retailer->id,
                'name' => $retailer->name,
                'email' => $retailer->email,
            ],
            'wallet_transactions' => $walletTransactions,
            'commission_transactions' => $commissionTransactions,
        ]);
    }

    public function transferToRetailer(Request $request, int $id)
    {
        $payload = $request->validate([
            'from_wallet_id' => 'required|integer|exists:wallets,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $distributor = $request->user();
        $retailer = $this->findOwnedRetailer($distributor->id, $id);

        return DB::transaction(function () use ($payload, $distributor, $retailer) {
            $fromWallet = Wallet::lockForUpdate()
                ->where('id', $payload['from_wallet_id'])
                ->where('user_id', $distributor->id)
                ->firstOrFail();

            $toWallet = Wallet::lockForUpdate()
                ->where('user_id', $retailer->id)
                ->orderBy('id')
                ->firstOrFail();

            $amount = (float) $payload['amount'];

            if ($fromWallet->is_frozen || $toWallet->is_frozen) {
                return response()->json(['message' => 'Cannot transfer to/from frozen wallets'], 422);
            }
            if ($fromWallet->balance < $amount) {
                return response()->json(['message' => 'Insufficient balance'], 422);
            }

            $transaction = Transaction::create([
                'user_id' => $distributor->id,
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id' => $toWallet->id,
                'type' => 'transfer',
                'amount' => $amount,
                'reference' => Transaction::generateReference(),
                'description' => $payload['description'] ?? 'Transfer to retailer: ' . $retailer->name,
                'status' => 'completed',
                'metadata' => [
                    'transfer_context' => 'distributor_to_retailer',
                    'retailer_id' => $retailer->id,
                ],
            ]);

            $fromWallet->balance -= $amount;
            $toWallet->balance += $amount;
            $fromWallet->save();
            $toWallet->save();

            RetailerController::notify(
                $retailer->id,
                'wallet_updated',
                'Wallet Updated',
                'Balance transferred by distributor to your wallet.',
                [
                    'wallet_id' => $toWallet->id,
                    'amount' => $amount,
                    'new_balance' => (float) $toWallet->balance,
                ]
            );

            return response()->json([
                'message' => 'Transfer to retailer completed successfully',
                'transaction' => $transaction->load(['fromWallet', 'toWallet']),
            ]);
        });
    }

    public function withdrawRequests(Request $request)
    {
        $distributor = $request->user();
        $retailerIds = User::where('role', 'retailer')
            ->where('distributor_id', $distributor->id)
            ->pluck('id');

        $requests = WithdrawRequest::with(['user', 'wallet', 'reviewer'])
            ->whereIn('user_id', $retailerIds)
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get();

        return response()->json($requests);
    }

    public function approveWithdrawRequest(Request $request, int $id)
    {
        $payload = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $distributor = $request->user();

        return DB::transaction(function () use ($id, $payload, $distributor) {
            $wr = WithdrawRequest::with(['user', 'wallet'])->lockForUpdate()->findOrFail($id);
            if ($wr->user?->distributor_id !== $distributor->id) {
                return response()->json(['message' => 'Unauthorized retailer withdraw request'], 403);
            }
            if (!in_array($wr->status, ['pending', 'approved'], true)) {
                return response()->json(['message' => 'Only pending/approved requests can be processed'], 422);
            }

            $wallet = $wr->wallet;
            if (!$wallet) {
                return response()->json(['message' => 'Insufficient balance for processing'], 422);
            }

            if (isset($wr->metadata['withdrawal_fee'])) {
                $feeAmount = (float) $wr->metadata['withdrawal_fee'];
            } else {
                $feeAmount = WithdrawalChargeService::calculateRetailerWithdrawalCharge((float) $wr->amount, (string) $wr->user->role);
            }

            $totalDebitAmount = (float) $wr->amount + $feeAmount;
            if ((float) $wallet->balance < $totalDebitAmount) {
                return response()->json(['message' => 'Insufficient balance for processing'], 422);
            }

            $tx = $wr->user->transactions()->create([
                'from_wallet_id' => $wallet->id,
                'type' => 'withdraw',
                'amount' => $wr->amount,
                'reference' => Transaction::generateReference(),
                'description' => 'Bank withdrawal (approved by distributor)',
                'status' => 'completed',
                'metadata' => [
                    'withdraw_request_id' => $wr->id,
                    'approved_by' => 'distributor',
                    'original_amount' => $wr->amount,
                    'debited_amount' => $totalDebitAmount,
                    'withdrawal_fee' => $feeAmount,
                    'net_payout_amount' => (float) $wr->amount,
                    'remarks' => $payload['remarks'] ?? null,
                ],
            ]);

            $wallet->balance -= $totalDebitAmount;
            $wallet->save();

            if ($feeAmount > 0) {
                $adminUser = User::where('role', 'admin')->lockForUpdate()->first();
                if (!$adminUser) {
                    return response()->json(['message' => 'Admin user not found for fee credit'], 500);
                }

                $adminMainWallet = Wallet::lockForUpdate()
                    ->where('user_id', $adminUser->id)
                    ->where('type', 'main')
                    ->first();

                if (!$adminMainWallet) {
                    return response()->json(['message' => 'Admin main wallet not found for fee credit'], 500);
                }

                $adminMainWallet->balance = (float) $adminMainWallet->balance + (float) $feeAmount;
                $adminMainWallet->save();
            }

            WalletLimit::updateLimit($wr->user_id, (float) $wr->amount, 'daily');
            WalletLimit::updateLimit($wr->user_id, (float) $wr->amount, 'monthly');

            $wr->status = 'processed';
            $wr->remarks = $payload['remarks'] ?? $wr->remarks;
            $wr->reviewed_by = $distributor->id;
            $wr->reviewed_at = now();
            $wr->save();

            RetailerController::notify(
                $wr->user_id,
                'withdraw_processed',
                'Withdraw Approved',
                'Your withdrawal has been approved and processed.',
                [
                    'withdraw_request_id' => $wr->id,
                    'transaction_id' => $tx->id,
                    'amount' => (float) $wr->amount,
                ]
            );

            return response()->json([
                'message' => 'Withdraw request approved and processed successfully',
            ]);
        });
    }

    public function rejectWithdrawRequest(Request $request, int $id)
    {
        $payload = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $distributor = $request->user();
        $wr = WithdrawRequest::with('user')->findOrFail($id);
        if ($wr->user?->distributor_id !== $distributor->id) {
            return response()->json(['message' => 'Unauthorized retailer withdraw request'], 403);
        }
        if (!in_array($wr->status, ['pending', 'approved'], true)) {
            return response()->json(['message' => 'Only pending/approved requests can be rejected'], 422);
        }

        $wr->status = 'rejected';
        $wr->remarks = $payload['remarks'] ?? $wr->remarks;
        $wr->reviewed_by = $distributor->id;
        $wr->reviewed_at = now();
        $wr->save();

        RetailerController::notify(
            $wr->user_id,
            'withdraw_rejected',
            'Withdraw Rejected',
            'Your withdrawal request was rejected.',
            [
                'withdraw_request_id' => $wr->id,
                'remarks' => $wr->remarks,
            ]
        );

        return response()->json([
            'message' => 'Withdraw request rejected successfully',
        ]);
    }

    public function transactions(Request $request)
    {
        $distributor = $request->user();

        $walletTransactions = $distributor->transactions()
            ->with(['fromWallet', 'toWallet'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $commissionTransactions = CommissionTransaction::where('user_id', $distributor->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'wallet_transactions' => $walletTransactions,
            'commission_transactions' => $commissionTransactions,
        ]);
    }

    private function findOwnedRetailer(int $distributorId, int $retailerId): User
    {
        return User::where('id', $retailerId)
            ->where('role', 'retailer')
            ->where('distributor_id', $distributorId)
            ->firstOrFail();
    }

    private function buildPerformanceData(int $distributorId): array
    {
        $retailerIds = User::where('role', 'retailer')
            ->where('distributor_id', $distributorId)
            ->pluck('id');

        $now = now();
        $weekStart = $now->copy()->startOfWeek();
        $monthStart = $now->copy()->startOfMonth();

        $weekWithdraw = (float) Transaction::whereIn('user_id', $retailerIds)
            ->where('type', 'withdraw')
            ->whereBetween('created_at', [$weekStart, $now])
            ->sum('amount');

        $monthWithdraw = (float) Transaction::whereIn('user_id', $retailerIds)
            ->where('type', 'withdraw')
            ->whereBetween('created_at', [$monthStart, $now])
            ->sum('amount');

        $commissionMonth = (float) CommissionTransaction::where('user_id', $distributorId)
            ->whereBetween('created_at', [$monthStart, $now])
            ->sum('commission_amount');

        $bonusCommission = round($commissionMonth * 0.05, 2);
        $performanceIncentive = $monthWithdraw >= 100000 ? 1000 : ($monthWithdraw >= 50000 ? 500 : 0);
        $targetRewards = $weekWithdraw >= 25000 ? 300 : 0;

        $weeklyChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $label = $day->format('D');
            $weeklyChart[] = [
                'label' => $label,
                'withdraw' => (float) Transaction::whereIn('user_id', $retailerIds)
                    ->where('type', 'withdraw')
                    ->whereDate('created_at', $day->toDateString())
                    ->sum('amount'),
            ];
        }

        $monthlyChart = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $now->copy()->subMonths($i);
            $start = $m->copy()->startOfMonth();
            $end = $m->copy()->endOfMonth();
            $monthlyChart[] = [
                'label' => $m->format('M Y'),
                'withdraw' => (float) Transaction::whereIn('user_id', $retailerIds)
                    ->where('type', 'withdraw')
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('amount'),
                'commission' => (float) CommissionTransaction::where('user_id', $distributorId)
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('commission_amount'),
            ];
        }

        return [
            'retailer_withdraw_summary' => [
                'weekly' => $weekWithdraw,
                'monthly' => $monthWithdraw,
            ],
            'weekly_chart' => $weeklyChart,
            'monthly_chart' => $monthlyChart,
            'bonus' => [
                'bonus_commission' => $bonusCommission,
                'performance_incentive' => $performanceIncentive,
                'target_rewards' => $targetRewards,
                'total_bonus' => round($bonusCommission + $performanceIncentive + $targetRewards, 2),
            ],
        ];
    }

}
