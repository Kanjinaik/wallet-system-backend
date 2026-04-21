<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\RetailerController;
use App\Models\CommissionTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawRequest;
use App\Services\CommissionSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MasterDistributorController extends Controller
{
    public function dashboard(Request $request)
    {
        $master = $request->user();
        $distributors = $this->ownedDistributorsQuery($master->id)
            ->with(['wallets', 'commissionOverride'])
            ->orderBy('created_at', 'desc')
            ->get();
        $commissionSummary = CommissionSummaryService::forUser($master->id);

        return response()->json([
            'wallet_balance' => (float) $master->wallets()->sum('balance'),
            'commission_earned' => $commissionSummary['earned'],
            'commission_available' => $commissionSummary['available'],
            'total_distributors' => $distributors->count(),
            'master_profile' => [
                'id' => $master->id,
                'name' => $master->name,
                'email' => $master->email,
                'phone' => $master->phone,
                'date_of_birth' => $master->date_of_birth,
                'is_active' => (bool) $master->is_active,
            ],
            'distributors' => $distributors->map(fn ($distributor) => $this->formatDistributorPayload($distributor))->values(),
        ]);
    }

    public function distributors(Request $request)
    {
        $distributors = $this->ownedDistributorsQuery($request->user()->id)
            ->with(['wallets', 'commissionOverride'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($distributor) => $this->formatDistributorPayload($distributor))
            ->values();

        return response()->json($distributors);
    }

    public function createDistributor(Request $request)
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

        $master = $request->user();

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

        $distributor = User::create([
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
            'role' => 'super_distributor',
            'distributor_id' => $master->id,
            'is_active' => true,
        ]);

        $distributor->wallets()->create([
            'name' => 'Super Distributor Wallet',
            'type' => 'sub',
            'balance' => 0,
        ]);

        $distributor->walletLimits()->createMany([
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
                'max_amount' => 200000,
            ],
        ]);

        return response()->json([
            'message' => 'Super distributor created successfully',
            'distributor' => [
                'id' => $distributor->id,
                'name' => $distributor->name,
                'email' => $distributor->email,
                'phone' => $distributor->phone,
                'date_of_birth' => $distributor->date_of_birth,
                'is_active' => (bool) $distributor->is_active,
                'balance' => 0,
            ],
        ], 201);
    }

    public function updateDistributor(Request $request, int $id)
    {
        $payload = $request->validate([
            'name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'alternate_mobile' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'business_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'kyc_document_type' => 'nullable|string|max:32',
            'kyc_id_number' => 'nullable|string|max:64',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
            'bank_ifsc_code' => 'nullable|string|max:32',
            'bank_name' => 'nullable|string|max:255',
            'admin_commission' => 'nullable|numeric|min:0|max:100',
            'distributor_commission' => 'nullable|numeric|min:0|max:100',
        ]);

        $distributor = $this->findOwnedDistributor($request->user()->id, $id);

        foreach ([
            'name',
            'last_name',
            'phone',
            'alternate_mobile',
            'date_of_birth',
            'business_name',
            'address',
            'city',
            'state',
            'kyc_document_type',
            'kyc_id_number',
            'bank_account_name',
            'bank_account_number',
            'bank_ifsc_code',
            'bank_name',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $distributor->{$field} = $payload[$field];
            }
        }

        $distributor->save();

        if (array_key_exists('admin_commission', $payload) || array_key_exists('distributor_commission', $payload)) {
            $distributor->commissionOverride()->updateOrCreate(
                ['user_id' => $distributor->id],
                [
                    'admin_commission' => $payload['admin_commission'] ?? 0,
                    'distributor_commission' => $payload['distributor_commission'] ?? 0,
                    'is_active' => true,
                ]
            );
        }

        return response()->json(['message' => 'Super distributor updated successfully']);
    }

    public function toggleDistributor(Request $request, int $id)
    {
        $distributor = $this->findOwnedDistributor($request->user()->id, $id);
        $distributor->is_active = !$distributor->is_active;
        $distributor->save();

        return response()->json([
            'message' => 'Super distributor ' . ($distributor->is_active ? 'activated' : 'deactivated') . ' successfully',
            'is_active' => (bool) $distributor->is_active,
        ]);
    }

    public function distributorTransactions(Request $request, int $id)
    {
        $distributor = $this->findOwnedDistributor($request->user()->id, $id);

        $walletTransactions = $distributor->transactions()
            ->with(['fromWallet', 'toWallet'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $commissionTransactions = CommissionTransaction::where('user_id', $distributor->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'distributor' => [
                'id' => $distributor->id,
                'name' => $distributor->name,
                'email' => $distributor->email,
            ],
            'wallet_transactions' => $walletTransactions,
            'commission_transactions' => $commissionTransactions,
        ]);
    }

    public function transferToDistributor(Request $request, int $id)
    {
        $payload = $request->validate([
            'from_wallet_id' => 'required|integer|exists:wallets,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $master = $request->user();
        $distributor = $this->findOwnedDistributor($master->id, $id);

        return DB::transaction(function () use ($payload, $master, $distributor) {
            $fromWallet = Wallet::lockForUpdate()
                ->where('id', $payload['from_wallet_id'])
                ->where('user_id', $master->id)
                ->firstOrFail();

            $toWallet = Wallet::lockForUpdate()
                ->where('user_id', $distributor->id)
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
                'user_id' => $master->id,
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id' => $toWallet->id,
                'type' => 'transfer',
                'amount' => $amount,
                'reference' => Transaction::generateReference(),
                'description' => $payload['description'] ?? 'Transfer to distributor: ' . $distributor->name,
                'status' => 'completed',
                'metadata' => [
                    'transfer_context' => 'master_to_super_distributor',
                    'distributor_id' => $distributor->id,
                ],
            ]);

            $fromWallet->balance -= $amount;
            $toWallet->balance += $amount;
            $fromWallet->save();
            $toWallet->save();

            RetailerController::notify(
                $distributor->id,
                'wallet_updated',
                'Wallet Updated',
                'Balance transferred by master distributor to your wallet.',
                [
                    'wallet_id' => $toWallet->id,
                    'amount' => $amount,
                    'new_balance' => (float) $toWallet->balance,
                ]
            );

            return response()->json([
                'message' => 'Transfer to super distributor completed successfully',
                'transaction' => $transaction->load(['fromWallet', 'toWallet']),
            ]);
        });
    }

    public function transactions(Request $request)
    {
        $master = $request->user();

        $walletTransactions = $master->transactions()
            ->with(['fromWallet', 'toWallet'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $commissionTransactions = CommissionTransaction::where('user_id', $master->id)
            ->with(['originalTransaction.user.distributor.distributor'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'wallet_transactions' => $walletTransactions,
            'commission_transactions' => $commissionTransactions,
        ]);
    }

    private function ownedDistributorsQuery(int $masterId)
    {
        return User::where('role', 'super_distributor')
            ->where('distributor_id', $masterId);
    }

    private function findOwnedDistributor(int $masterId, int $distributorId): User
    {
        return $this->ownedDistributorsQuery($masterId)
            ->where('id', $distributorId)
            ->firstOrFail();
    }

    private function formatDistributorPayload(User $distributor): array
    {
        return [
            'id' => $distributor->id,
            'name' => $distributor->name,
            'last_name' => $distributor->last_name,
            'email' => $distributor->email,
            'phone' => $distributor->phone,
            'alternate_mobile' => $distributor->alternate_mobile,
            'date_of_birth' => $distributor->date_of_birth,
            'business_name' => $distributor->business_name,
            'address' => $distributor->address,
            'city' => $distributor->city,
            'state' => $distributor->state,
            'kyc_document_type' => $distributor->kyc_document_type,
            'kyc_id_number' => $distributor->kyc_id_number,
            'kyc_status' => $distributor->kyc_status,
            'kyc_liveness_verified' => (bool) $distributor->kyc_liveness_verified,
            'bank_account_name' => $distributor->bank_account_name,
            'bank_account_number' => $distributor->bank_account_number,
            'bank_ifsc_code' => $distributor->bank_ifsc_code,
            'bank_name' => $distributor->bank_name,
            'admin_commission' => (float) ($distributor->commissionOverride->admin_commission ?? 0),
            'distributor_commission' => (float) ($distributor->commissionOverride->distributor_commission ?? 0),
            'is_active' => (bool) $distributor->is_active,
            'balance' => (float) $distributor->wallets->sum('balance'),
            'total_retailers' => User::whereIn('distributor_id', User::where('role', 'distributor')->where('distributor_id', $distributor->id)->pluck('id'))->where('role', 'retailer')->count(),
        ];
    }
}
