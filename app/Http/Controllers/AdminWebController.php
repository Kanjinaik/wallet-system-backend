<?php

namespace App\Http\Controllers;

use App\Models\AdminActionLog;
use App\Models\AdminSetting;
use App\Models\CommissionConfig;
use App\Models\CommissionOverride;
use App\Models\CommissionTransaction;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wallet;
use App\Models\WalletAdjustment;
use App\Models\WalletLimit;
use App\Models\WithdrawRequest;
use App\Services\ErtitechPayoutService;
use App\Services\WithdrawalChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class AdminWebController extends Controller
{
    public function dashboard()
    {
        return view('admin.panel', array_merge($this->baseData(), ['tab' => 'dashboard']));
    }

    public function users(Request $request)
    {
        $section = (string) $request->query('section', 'users');
        if (!in_array($section, ['users', 'add-user', 'roles'], true)) {
            $section = 'users';
        }

        return view('admin.panel', array_merge($this->baseData(), [
            'tab' => 'users',
            'userSection' => $section,
        ]));
    }

    public function wallets()
    {
        return view('admin.panel', array_merge($this->baseData(), ['tab' => 'wallets']));
    }

    public function walletTransfer()
    {
        return redirect()->route('admin.wallets');
    }

    public function commissions()
    {
        return view('admin.panel', array_merge($this->baseData(), ['tab' => 'commissions']));
    }

    public function withdrawals()
    {
        return view('admin.panel', array_merge($this->baseData(), ['tab' => 'withdrawals']));
    }

    public function support(Request $request)
    {
        $threads = SupportThread::with([
            'user',
            'admin',
            'messages' => fn($q) => $q->orderBy('created_at'),
        ])->orderByDesc('updated_at')->get();

        $activeThreadId = (int) $request->query('thread_id', $threads->first()->id ?? 0);
        $activeThread = $threads->firstWhere('id', $activeThreadId) ?? $threads->first();

        return view('admin.panel', array_merge($this->baseData(), [
            'tab' => 'support',
            'supportThreads' => $threads,
            'activeSupportThread' => $activeThread,
        ]));
    }

    public function supportReply(Request $request, int $threadId)
    {
        $thread = SupportThread::findOrFail($threadId);

        $data = $request->validate([
            'message' => ['required', 'string'],
            'status'  => ['nullable', 'in:open,in_progress,escalated,resolved'],
        ]);

        SupportMessage::create([
            'support_thread_id' => $thread->id,
            'sender_type' => 'admin',
            'sender_id' => auth()->id(),
            'message' => $data['message'],
        ]);

        $thread->updated_at = now();
        if (!$thread->admin_id) {
            $thread->admin_id = auth()->id();
        }
        if (!empty($data['status'])) {
            $thread->status = $data['status'];
        } elseif ($thread->status === 'open') {
            $thread->status = 'in_progress';
        }
        $thread->save();

        return redirect()->route('admin.support', ['thread_id' => $thread->id])
            ->with('success', 'Reply sent.');
    }

    public function withdrawCharges()
    {
        return redirect()->route('admin.withdrawals');
    }

    public function transactions(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $type = $request->query('type');

        $withdrawals = Transaction::with(['user', 'fromWallet'])
            ->where('type', 'withdraw')
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('reference', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhereHas('user', function ($uq) use ($query) {
                            $uq->where('name', 'like', "%{$query}%")
                                ->orWhere('email', 'like', "%{$query}%");
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->limit(250)
            ->get();

        $deposits = Transaction::with(['user', 'toWallet'])
            ->where('type', 'deposit')
            ->when(in_array($type, ['pending', 'completed', 'failed', 'cancelled'], true), fn($builder) => $builder->where('status', $type))
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('reference', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhereHas('user', function ($uq) use ($query) {
                            $uq->where('name', 'like', "%{$query}%")
                                ->orWhere('email', 'like', "%{$query}%");
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->limit(250)
            ->get();

        $recharges = Transaction::with(['user', 'fromWallet'])
            ->where('type', 'recharge')
            ->when(in_array($type, ['pending', 'completed', 'failed', 'cancelled'], true), fn($builder) => $builder->where('status', $type))
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('reference', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhereHas('user', function ($uq) use ($query) {
                            $uq->where('name', 'like', "%{$query}%")
                                ->orWhere('email', 'like', "%{$query}%");
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->limit(250)
            ->get();

        return view('admin.panel', array_merge($this->baseData(), [
            'tab' => 'transactions',
            'search' => $query,
            'typeFilter' => $type,
            'recentWithdrawals' => $withdrawals,
            'recentDeposits' => $deposits,
            'recentRecharges' => $recharges,
        ]));
    }

    public function approveRecharge(int $id)
    {
        $transaction = Transaction::with('user')->where('type', 'recharge')->findOrFail($id);
        if ($transaction->status !== 'pending') {
            return back()->with('error', 'Only pending recharge requests can be approved.');
        }

        $metadata = $transaction->metadata ?? [];
        $metadata['admin_manual_recharge_action'] = 'approved';
        $metadata['admin_manual_recharge_action_at'] = now()->toIso8601String();
        $metadata['admin_manual_recharge_action_by'] = auth()->id();
        $transaction->metadata = $metadata;
        $transaction->status = 'completed';
        $transaction->save();

        $this->logAdminAction('approve_recharge', 'transaction', $transaction->id, [
            'reference' => $transaction->reference,
            'user_id' => $transaction->user_id,
        ]);

        return back()->with('success', 'Recharge marked as completed.');
    }

    public function rejectRecharge(int $id)
    {
        $transaction = Transaction::with('user')->where('type', 'recharge')->findOrFail($id);
        if ($transaction->status !== 'pending') {
            return back()->with('error', 'Only pending recharge requests can be rejected.');
        }

        $metadata = $transaction->metadata ?? [];
        $metadata['admin_manual_recharge_action'] = 'rejected';
        $metadata['admin_manual_recharge_action_at'] = now()->toIso8601String();
        $metadata['admin_manual_recharge_action_by'] = auth()->id();
        $transaction->metadata = $metadata;
        $transaction->status = 'failed';
        $transaction->save();

        app(\App\Services\RetailerRechargeStatusService::class)->refundWalletForFailure($transaction);

        $this->logAdminAction('reject_recharge', 'transaction', $transaction->id, [
            'reference' => $transaction->reference,
            'user_id' => $transaction->user_id,
        ]);

        return back()->with('success', 'Recharge marked as failed and wallet refunded.');
    }

    public function reports()
    {
        return view('admin.panel', array_merge($this->baseData(), ['tab' => 'reports']));
    }

    public function apiManagement()
    {
        return view('admin.panel', array_merge($this->baseData(), ['tab' => 'api-management']));
    }

    public function settings()
    {
        return view('admin.panel', array_merge($this->baseData(), ['tab' => 'settings']));
    }

    public function logs()
    {
        return view('admin.panel', array_merge($this->baseData(), ['tab' => 'logs']));
    }

    public function security()
    {
        return view('admin.panel', array_merge($this->baseData(), ['tab' => 'security']));
    }

    public function profile()
    {
        return view('admin.panel', array_merge($this->baseData(), ['tab' => 'profile']));
    }

    public function markNotificationRead(int $id)
    {
        $admin = auth()->user();
        if (!$admin) {
            return redirect()->route('admin.login.form');
        }

        $notification = $admin->notifications()->findOrFail($id);
        $notification->is_read = true;
        $notification->save();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllNotificationsRead()
    {
        $admin = auth()->user();
        if (!$admin) {
            return redirect()->route('admin.login.form');
        }

        $admin->notifications()->where('is_read', false)->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function media(string $path)
    {
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }

    public function updateProfilePhoto(Request $request)
    {
        $payload = $request->validate([
            'profile_photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $admin = auth()->user();
        if (!$admin) {
            return redirect()->route('admin.login.form');
        }

        $admin->profile_photo_path = $request->file('profile_photo')->store('users/profile-photo', 'public');
        $admin->save();

        $this->logAdminAction('update_profile_photo', 'user', $admin->id, [
            'mime' => $payload['profile_photo']->getClientMimeType(),
        ]);

        return redirect()->route('admin.profile')->with('success', 'Profile photo updated successfully.');
    }

    public function editUser(int $id)
    {
        $user = User::with(['distributor', 'commissionOverride'])->findOrFail($id);

        return view('admin.user-edit', [
            'admin' => auth()->user(),
            'user' => $user,
            'masterDistributors' => User::where('role', 'master_distributor')->orderBy('name')->get(),
            'superDistributors' => User::where('role', 'super_distributor')->orderBy('name')->get(),
            'distributors' => User::where('role', 'distributor')->orderBy('name')->get(),
            'defaultCommission' => CommissionConfig::where('is_active', true)->where('user_role', $user->role)->first(),
        ]);
    }

    public function userProfile(int $id)
    {
        $user = User::with(['distributor', 'wallets', 'walletLimits', 'commissionOverride'])->findOrFail($id);
        $defaultCommission = CommissionConfig::where('is_active', true)
            ->where('user_role', $user->role)
            ->first();

        return view('admin.user-profile', [
            'admin' => auth()->user(),
            'user' => $user,
            'defaultCommission' => $defaultCommission,
        ]);
    }

    public function userHistory(int $id)
    {
        $user = User::findOrFail($id);

        $walletTransactions = $user->transactions()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Transaction $transaction) use ($user) {
                return [
                    'id' => 'txn-' . $transaction->id,
                    'source' => 'wallet',
                    'type' => (string) $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'status' => (string) ($transaction->status ?: 'completed'),
                    'reference' => (string) ($transaction->reference ?: ('TXN-' . $transaction->id)),
                    'description' => (string) ($transaction->description ?: ''),
                    'name' => (string) ($user->name ?: ''),
                    'created_at' => optional($transaction->created_at)->toIso8601String(),
                ];
            });

        $commissionTransactions = CommissionTransaction::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (CommissionTransaction $transaction) use ($user) {
                return [
                    'id' => 'comm-' . $transaction->id,
                    'source' => 'commission',
                    'type' => 'commission',
                    'amount' => (float) $transaction->commission_amount,
                    'status' => 'completed',
                    'reference' => (string) ($transaction->reference ?: ('COMM-' . $transaction->id)),
                    'description' => (string) ($transaction->description ?: ''),
                    'name' => (string) ($user->name ?: ''),
                    'created_at' => optional($transaction->created_at)->toIso8601String(),
                ];
            });

        $records = $walletTransactions
            ->concat($commissionTransactions)
            ->sortByDesc('created_at')
            ->values()
            ->all();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
            'records' => $records,
        ]);
    }

    public function createUser(Request $request)
    {
        $request->merge([
            'phone' => preg_replace('/\D+/', '', (string) $request->input('phone', '')),
            'alternate_mobile' => preg_replace('/\D+/', '', (string) $request->input('alternate_mobile', '')),
        ]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:master_distributor,super_distributor,distributor,retailer',
            'phone' => 'required|string|digits:10',
            'alternate_mobile' => 'nullable|string|digits:10',
            'business_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'profile_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'kyc_id_number' => 'nullable|string|max:64',
            'kyc_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'address_proof_front' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'address_proof_back' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'pan_number' => 'nullable|string|max:20',
            'pan_proof_front' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'pan_proof_back' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'distributor_id' => 'nullable|integer|exists:users,id',
            'opening_balance' => 'nullable|numeric|min:0',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:64',
            'bank_ifsc_code' => 'nullable|string|max:32',
            'bank_name' => 'nullable|string|max:255',
            'admin_commission' => 'nullable|numeric|min:0|max:100',
            'distributor_commission' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.users', ['section' => 'add-user'])
                ->withErrors($validator)
                ->withInput();
        }

        $payload = $validator->validated();

        $profilePhotoPath = $request->hasFile('profile_photo')
            ? $request->file('profile_photo')->store('users/profile-photo', 'public')
            : null;
        $kycPhotoPath = $request->hasFile('kyc_photo')
            ? $request->file('kyc_photo')->store('users/kyc-photo', 'public')
            : null;
        $addressProofFrontPath = $request->hasFile('address_proof_front')
            ? $request->file('address_proof_front')->store('users/address-proof/front', 'public')
            : null;
        $addressProofBackPath = $request->hasFile('address_proof_back')
            ? $request->file('address_proof_back')->store('users/address-proof/back', 'public')
            : null;
        $panProofFrontPath = $request->hasFile('pan_proof_front')
            ? $request->file('pan_proof_front')->store('users/pan-proof/front', 'public')
            : null;
        $panProofBackPath = $request->hasFile('pan_proof_back')
            ? $request->file('pan_proof_back')->store('users/pan-proof/back', 'public')
            : null;

        if ($payload['role'] === 'retailer' && empty($payload['distributor_id'])) {
            return back()->withInput()->with('error', 'Distributor is required for retailer.');
        }
        if ($payload['role'] === 'super_distributor' && empty($payload['distributor_id'])) {
            return back()->withInput()->with('error', 'Master distributor is required for super distributor.');
        }
        if ($payload['role'] === 'super_distributor' && !empty($payload['distributor_id'])) {
            $validMaster = User::where('id', $payload['distributor_id'])->where('role', 'master_distributor')->exists();
            if (!$validMaster) {
                return back()->withInput()->with('error', 'Valid master distributor is required for super distributor.');
            }
        }
        if ($payload['role'] === 'distributor' && empty($payload['distributor_id'])) {
            return back()->withInput()->with('error', 'Super distributor is required for distributor.');
        }
        if ($payload['role'] === 'distributor' && !empty($payload['distributor_id'])) {
            $validSuper = User::where('id', $payload['distributor_id'])->where('role', 'super_distributor')->exists();
            if (!$validSuper) {
                return back()->withInput()->with('error', 'Valid super distributor is required for distributor.');
            }
        }
        if ($payload['role'] === 'retailer' && !empty($payload['distributor_id'])) {
            $validDistributor = User::where('id', $payload['distributor_id'])->where('role', 'distributor')->exists();
            if (!$validDistributor) {
                return back()->withInput()->with('error', 'Valid distributor is required for retailer.');
            }
        }

        $newUser = DB::transaction(function () use (
            $payload,
            $profilePhotoPath,
            $kycPhotoPath,
            $addressProofFrontPath,
            $addressProofBackPath,
            $panProofFrontPath,
            $panProofBackPath
        ) {
            $user = User::create([
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
                'date_of_birth' => $payload['date_of_birth'] ?? null,
                'profile_photo_path' => $profilePhotoPath,
                'kyc_id_number' => $payload['kyc_id_number'] ?? null,
                'pan_number' => isset($payload['pan_number']) ? strtoupper((string) $payload['pan_number']) : null,
                'pan_proof_front_path' => $panProofFrontPath,
                'pan_proof_back_path' => $panProofBackPath,
                'kyc_photo_path' => $kycPhotoPath,
                'address_proof_front_path' => $addressProofFrontPath,
                'address_proof_back_path' => $addressProofBackPath,
                'kyc_document_path' => $kycPhotoPath,
                'role' => $payload['role'],
                'distributor_id' => in_array($payload['role'], ['super_distributor', 'distributor', 'retailer'], true)
                    ? ($payload['distributor_id'] ?? null)
                    : null,
                'is_active' => true,
                'bank_account_name' => $payload['bank_account_name'] ?? null,
                'bank_account_number' => $payload['bank_account_number'] ?? null,
                'bank_ifsc_code' => $payload['bank_ifsc_code'] ?? null,
                'bank_name' => $payload['bank_name'] ?? null,
            ]);

            $user->wallets()->create([
                'name' => ucfirst($payload['role']) . ' Sub Wallet',
                'type' => 'sub',
                'balance' => (float) ($payload['opening_balance'] ?? 0),
                'is_frozen' => false,
            ]);

            $daily = in_array($payload['role'], ['master_distributor', 'super_distributor', 'distributor'], true) ? 500000 : 500000;
            $monthly = in_array($payload['role'], ['master_distributor', 'super_distributor', 'distributor'], true) ? 5000000 : 500000;
            $perTx = in_array($payload['role'], ['master_distributor', 'super_distributor', 'distributor'], true) ? 200000 : 500000;

            $user->walletLimits()->createMany([
                ['limit_type' => 'daily', 'max_amount' => $daily, 'reset_date' => now()->toDateString()],
                ['limit_type' => 'monthly', 'max_amount' => $monthly, 'reset_date' => now()->startOfMonth()->toDateString()],
                ['limit_type' => 'per_transaction', 'max_amount' => $perTx],
            ]);

            $adminCommission = array_key_exists('admin_commission', $payload) ? $payload['admin_commission'] : null;
            $distributorCommission = array_key_exists('distributor_commission', $payload) ? $payload['distributor_commission'] : null;
            if ($adminCommission !== null || $distributorCommission !== null) {
                CommissionOverride::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'admin_commission' => (float) ($adminCommission ?? 0),
                        'distributor_commission' => (float) ($distributorCommission ?? 0),
                        'is_active' => true,
                    ]
                );
            }

            return $user;
        });

        $this->logAdminAction('create_user', 'user', $newUser->id, ['role' => $newUser->role]);
        return redirect()->route('admin.users', ['section' => 'users'])
            ->with('success', ucfirst($payload['role']) . ' created successfully.');
    }

    public function toggleUser(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->role === 'admin') {
            return back()->with('error', 'Admin user cannot be deactivated from this action.');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $this->logAdminAction('toggle_user_status', 'user', $user->id, ['is_active' => $user->is_active]);
        return back()->with('success', 'User ' . ($user->is_active ? 'activated' : 'deactivated') . ' successfully.');
    }

    public function updateUser(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $payload = $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'required|string|digits:10',
            'alternate_mobile' => 'nullable|string|digits:10',
            'business_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'role' => 'required|in:admin,master_distributor,super_distributor,distributor,retailer',
            'distributor_id' => 'nullable|integer|exists:users,id',
            'kyc_id_number' => 'nullable|string|max:64',
            'kyc_document_type' => 'nullable|string|max:50',
            'kyc_status' => 'nullable|string|max:30',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:64',
            'bank_ifsc_code' => 'nullable|string|max:32',
            'bank_name' => 'nullable|string|max:255',
            'profile_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'kyc_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'address_proof_front' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'address_proof_back' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'admin_commission' => 'nullable|numeric|min:0|max:100',
            'distributor_commission' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($payload['role'] === 'retailer' && empty($payload['distributor_id'])) {
            return back()->withInput()->with('error', 'Distributor is required for retailer.');
        }
        if ($payload['role'] === 'super_distributor' && empty($payload['distributor_id'])) {
            return back()->withInput()->with('error', 'Master distributor is required for super distributor.');
        }
        if ($payload['role'] === 'super_distributor' && !empty($payload['distributor_id'])) {
            $validMaster = User::where('id', $payload['distributor_id'])->where('role', 'master_distributor')->exists();
            if (!$validMaster) {
                return back()->withInput()->with('error', 'Valid master distributor is required for super distributor.');
            }
        }
        if ($payload['role'] === 'distributor' && empty($payload['distributor_id'])) {
            return back()->withInput()->with('error', 'Super distributor is required for distributor.');
        }
        if ($payload['role'] === 'distributor' && !empty($payload['distributor_id'])) {
            $validSuper = User::where('id', $payload['distributor_id'])->where('role', 'super_distributor')->exists();
            if (!$validSuper) {
                return back()->withInput()->with('error', 'Valid super distributor is required for distributor.');
            }
        }
        if ($payload['role'] === 'retailer' && !empty($payload['distributor_id'])) {
            $validDistributor = User::where('id', $payload['distributor_id'])->where('role', 'distributor')->exists();
            if (!$validDistributor) {
                return back()->withInput()->with('error', 'Valid distributor is required for retailer.');
            }
        }

        DB::transaction(function () use ($request, $user, $payload) {
            $updateData = [
                'name' => $payload['name'],
                'last_name' => $payload['last_name'] ?? null,
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'alternate_mobile' => $payload['alternate_mobile'] ?? null,
                'business_name' => $payload['business_name'] ?? null,
                'address' => $payload['address'] ?? null,
                'city' => $payload['city'] ?? null,
                'state' => $payload['state'] ?? null,
                'date_of_birth' => $payload['date_of_birth'] ?? null,
                'role' => $payload['role'],
                'distributor_id' => in_array($payload['role'], ['super_distributor', 'distributor', 'retailer'], true)
                    ? ($payload['distributor_id'] ?? null)
                    : null,
                'kyc_id_number' => $payload['kyc_id_number'] ?? null,
                'kyc_document_type' => $payload['kyc_document_type'] ?? null,
                'kyc_status' => $payload['kyc_status'] ?? null,
                'bank_account_name' => $payload['bank_account_name'] ?? null,
                'bank_account_number' => $payload['bank_account_number'] ?? null,
                'bank_ifsc_code' => $payload['bank_ifsc_code'] ?? null,
                'bank_name' => $payload['bank_name'] ?? null,
            ];

            if ($request->hasFile('profile_photo')) {
                $updateData['profile_photo_path'] = $request->file('profile_photo')->store('users/profile-photo', 'public');
            }
            if ($request->hasFile('kyc_photo')) {
                $updateData['kyc_photo_path'] = $request->file('kyc_photo')->store('users/kyc-photo', 'public');
                $updateData['kyc_document_path'] = $updateData['kyc_photo_path'];
            }
            if ($request->hasFile('address_proof_front')) {
                $updateData['address_proof_front_path'] = $request->file('address_proof_front')->store('users/address-proof/front', 'public');
            }
            if ($request->hasFile('address_proof_back')) {
                $updateData['address_proof_back_path'] = $request->file('address_proof_back')->store('users/address-proof/back', 'public');
            }

            $user->update($updateData);

            $adminCommission = $payload['admin_commission'] ?? null;
            $distributorCommission = $payload['distributor_commission'] ?? null;

            if ($adminCommission !== null || $distributorCommission !== null) {
                CommissionOverride::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'admin_commission' => (float) ($adminCommission ?? 0),
                        'distributor_commission' => (float) ($distributorCommission ?? 0),
                        'is_active' => true,
                    ]
                );
            }
        });

        $this->logAdminAction('update_user', 'user', $user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);

        return redirect()->route('admin.users.edit', ['id' => $user->id])->with('success', 'User updated successfully.');
    }

    public function resetUserPassword(Request $request, int $id)
    {
        $payload = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::findOrFail($id);
        $user->password = Hash::make($payload['password']);
        $user->plain_password = $payload['password'];
        $user->save();

        $this->logAdminAction('reset_user_password', 'user', $user->id, []);
        return back()->with('success', 'Password reset successfully.');
    }

    public function deleteUser(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->role === 'admin') {
            return back()->with('error', 'Admin user cannot be deleted.');
        }
        $deletedId = $user->id;
        $user->delete();

        $this->logAdminAction('delete_user', 'user', $deletedId, []);
        return back()->with('success', 'User deleted successfully.');
    }

    public function deductUserBalance(Request $request, int $id)
    {
        $payload = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string|max:500',
        ]);

        $user = User::findOrFail($id);
        $userWallet = $user->wallets()->first();
        if (!$userWallet) {
            return back()->with('error', 'User wallet not found.');
        }

        $admin = auth()->user();
        $adminWallet = $admin->wallets()->first();
        if (!$adminWallet) {
            return back()->with('error', 'Admin wallet not found.');
        }

        $amount = (float) $payload['amount'];

        if ($userWallet->balance < $amount) {
            return back()->with('error', 'Insufficient balance in user wallet.');
        }

        DB::transaction(function () use ($userWallet, $adminWallet, $amount, $payload, $user, $admin) {
            $userWallet->balance -= $amount;
            $adminWallet->balance += $amount;
            $userWallet->save();
            $adminWallet->save();

            $description = $payload['remarks'] ?? 'Admin deducted balance';

            // Transaction for admin (credit)
            Transaction::create([
                'user_id' => $admin->id,
                'from_wallet_id' => $userWallet->id,
                'to_wallet_id' => $adminWallet->id,
                'type' => 'transfer',
                'amount' => $amount,
                'reference' => Transaction::generateReference(),
                'description' => $description,
                'status' => 'completed',
            ]);

            // Create WithdrawRequest for user (appears in payout history)
            WithdrawRequest::create([
                'user_id' => $user->id,
                'wallet_id' => $userWallet->id,
                'amount' => $amount,
                'net_amount' => $amount,
                'status' => 'processed',
                'remarks' => $description,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'metadata' => [
                    'type' => 'admin_deduction',
                    'admin_user_id' => $admin->id,
                    'admin_remarks' => $payload['remarks'] ?? null,
                ],
            ]);

            $this->logAdminAction('deduct_user_balance', 'user', $user->id, [
                'amount' => $amount,
                'remarks' => $payload['remarks'] ?? null,
            ]);
        });

        return back()->with('success', 'Balance deducted successfully from user wallet to admin wallet.');
    }

    public function toggleWallet(Request $request, int $id)
    {
        $wallet = Wallet::with('user')->findOrFail($id);

        if ($wallet->is_frozen) {
            try {
                DB::transaction(function () use ($wallet) {
                    $lockedWallet = Wallet::with('user')->lockForUpdate()->findOrFail($wallet->id);
                    $freezeReason = (string) ($lockedWallet->freeze_reason ?? '');
                    $frozenAmount = 0.0;

                    if (preg_match('/Amount:\s*([0-9]+(?:\.[0-9]{1,2})?)/', $freezeReason, $matches) === 1) {
                        $frozenAmount = (float) $matches[1];
                    }

                    if ($frozenAmount > 0) {
                        $lockedWallet->balance = (float) $lockedWallet->balance + $frozenAmount;

                        WalletAdjustment::create([
                            'admin_user_id' => auth()->id(),
                            'user_id' => $lockedWallet->user_id,
                            'wallet_id' => $lockedWallet->id,
                            'type' => 'add',
                            'amount' => $frozenAmount,
                            'reference' => WalletAdjustment::generateReference(),
                            'remarks' => 'Frozen amount returned on wallet unfreeze',
                        ]);
                    }

                    $lockedWallet->is_frozen = false;
                    $lockedWallet->freeze_reason = null;
                    $lockedWallet->save();

                    $this->logAdminAction('toggle_wallet_freeze', 'wallet', $lockedWallet->id, [
                        'is_frozen' => false,
                        'returned_amount' => $frozenAmount,
                    ]);
                });
            } catch (\RuntimeException $exception) {
                return back()->with('error', $exception->getMessage());
            }

            return back()->with('success', 'Wallet for ' . ($wallet->user?->name ?? 'user') . ' unfrozen and frozen amount returned.');
        }

        if (!in_array((string) $wallet->user?->role, ['retailer', 'user'], true)) {
            return back()->with('error', 'Freeze with amount is allowed only for retailer wallets.');
        }

        $payload = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($wallet, $payload) {
                $lockedWallet = Wallet::with('user')->lockForUpdate()->findOrFail($wallet->id);
                $amount = (float) $payload['amount'];

                if ($lockedWallet->is_frozen) {
                    throw new \RuntimeException('Wallet is already frozen.');
                }

                if ((float) $lockedWallet->balance < $amount) {
                    throw new \RuntimeException('Insufficient balance in retailer wallet for freeze.');
                }

                $lockedWallet->balance = (float) $lockedWallet->balance - $amount;
                $lockedWallet->is_frozen = true;
                $lockedWallet->freeze_reason = trim(
                    'Frozen by admin backend. Amount: ' . number_format($amount, 2, '.', '') .
                    (!empty($payload['remarks']) ? '. Remarks: ' . $payload['remarks'] : '')
                );
                $lockedWallet->save();

                WalletAdjustment::create([
                    'admin_user_id' => auth()->id(),
                    'user_id' => $lockedWallet->user_id,
                    'wallet_id' => $lockedWallet->id,
                    'type' => 'deduct',
                    'amount' => $amount,
                    'reference' => WalletAdjustment::generateReference(),
                    'remarks' => $payload['remarks'] ?? 'Retailer wallet frozen by admin',
                ]);

                $this->logAdminAction('toggle_wallet_freeze', 'wallet', $lockedWallet->id, [
                    'is_frozen' => true,
                    'amount' => $amount,
                    'remarks' => $payload['remarks'] ?? null,
                ]);
            });
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Retailer wallet for ' . ($wallet->user?->name ?? 'user') . ' frozen successfully.');
    }

    public function adjustWallet(Request $request)
    {
        $payload = $request->validate([
            'wallet_id' => 'required|integer|exists:wallets,id',
            'type' => 'required|in:add,deduct',
            'amount' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($payload) {
            $wallet = Wallet::with('user')->lockForUpdate()->findOrFail($payload['wallet_id']);
            $amount = (float) $payload['amount'];
            if ($payload['type'] === 'deduct' && $wallet->balance < $amount) {
                throw new \RuntimeException('Insufficient wallet balance for deduction.');
            }

            $wallet->balance = $payload['type'] === 'add'
                ? $wallet->balance + $amount
                : $wallet->balance - $amount;
            $wallet->save();

            WalletAdjustment::create([
                'admin_user_id' => auth()->id(),
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'type' => $payload['type'],
                'amount' => $amount,
                'reference' => WalletAdjustment::generateReference(),
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $this->logAdminAction('wallet_adjustment', 'wallet', $wallet->id, [
                'type' => $payload['type'],
                'amount' => $amount,
            ]);
        });

        return back()->with('success', 'Wallet adjusted successfully.');
    }

    public function updateDefaultCommission(Request $request)
    {
        $payload = $request->validate([
            'user_role' => 'required|in:admin,master_distributor,super_distributor,distributor,retailer',
            'admin_commission' => 'required|numeric|min:0|max:100',
            'distributor_commission' => 'required|numeric|min:0|max:100',
        ]);

        CommissionConfig::updateOrCreate(
            ['user_role' => $payload['user_role'], 'is_active' => true],
            [
                'name' => ucfirst($payload['user_role']) . ' Withdrawal Commission',
                'admin_commission' => $payload['admin_commission'],
                'distributor_commission' => $payload['distributor_commission'],
            ]
        );

        $this->logAdminAction('update_default_commission', 'commission_config', 0, $payload);
        return back()->with('success', 'Default commission updated.');
    }

    public function setCommissionOverride(Request $request)
    {
        $payload = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'admin_commission' => 'required|numeric|min:0|max:100',
            'distributor_commission' => 'required|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $override = CommissionOverride::updateOrCreate(
            ['user_id' => $payload['user_id']],
            [
                'admin_commission' => $payload['admin_commission'],
                'distributor_commission' => $payload['distributor_commission'],
                'is_active' => $payload['is_active'] ?? true,
            ]
        );

        $this->logAdminAction('set_commission_override', 'commission_override', $override->id, $payload);
        return back()->with('success', 'Commission override saved.');
    }

    public function deleteCommissionOverride(int $id)
    {
        $override = CommissionOverride::findOrFail($id);
        $overrideId = $override->id;
        $override->delete();
        $this->logAdminAction('delete_commission_override', 'commission_override', $overrideId, []);
        return back()->with('success', 'Commission override removed.');
    }

    public function updateWithdrawSettings(Request $request)
    {
        $payload = $request->validate([
            'withdraw_approval_mode' => 'required|in:auto,manual',
            'withdraw_min_amount' => 'required|numeric|min:0',
            'withdraw_max_per_tx' => 'required|numeric|min:0',
        ]);

        AdminSetting::setValue('withdraw_approval_mode', $payload['withdraw_approval_mode']);
        AdminSetting::setValue('withdraw_min_amount', $payload['withdraw_min_amount']);
        AdminSetting::setValue('withdraw_max_per_tx', $payload['withdraw_max_per_tx']);

        $this->logAdminAction('update_withdraw_settings', 'admin_setting', 0, $payload);
        return back()->with('success', 'Withdraw settings updated.');
    }

    public function approveWithdraw(Request $request, int $id)
    {
        $payload = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($id, $payload) {
                $wr = WithdrawRequest::with(['user', 'wallet'])->lockForUpdate()->findOrFail($id);
                if ($wr->status !== 'pending' && $wr->status !== 'approved') {
                    throw new \RuntimeException('Only pending/approved requests can be processed.');
                }

                $wallet = $wr->wallet;
                if (!$wallet) {
                    throw new \RuntimeException('Insufficient balance to process withdrawal.');
                }

                if (isset($wr->metadata['withdrawal_fee'])) {
                    $feeAmount = (float) $wr->metadata['withdrawal_fee'];
                } else {
                    $withdrawalAmount = (float) $wr->amount;
                    $feeAmount = WithdrawalChargeService::calculateRetailerWithdrawalCharge($withdrawalAmount, (string) $wr->user->role);
                }

                $netPayoutAmount = (float) $wr->amount;
                $totalDebitAmount = (float) $wr->amount + $feeAmount;
                $payoutService = app(ErtitechPayoutService::class);

                if ((float) $wallet->balance < $totalDebitAmount) {
                    throw new \RuntimeException('Insufficient balance to process withdrawal.');
                }

                try {
                    $payoutMeta = $payoutService->createPayout($wr, $wr->user, $netPayoutAmount);
                } catch (\Throwable $payoutException) {
                    $payoutMeta = [
                        'mode' => 'internal',
                        'status' => 'pending',
                        'fallback_reason' => $payoutException->getMessage(),
                    ];
                }

                $processingState = $payoutService->getPayoutProcessingState($payoutMeta);
                $transactionStatus = $processingState === 'completed' ? 'completed' : 'pending';
                $withdrawRequestStatus = $processingState === 'completed' ? 'processed' : 'pending';
                $notifyType = $processingState === 'completed' ? 'withdraw_processed' : 'withdraw_requested';
                $notifyTitle = $processingState === 'completed' ? 'Withdraw Approved' : 'Withdraw Initiated';
                $notifyMessage = $processingState === 'completed'
                    ? 'Your withdrawal has been approved and processed.'
                    : 'Your withdrawal has been approved and sent to the payout gateway. Bank confirmation is pending.';

                $tx = $wr->user->transactions()->create([
                    'from_wallet_id' => $wallet->id,
                    'type' => 'withdraw',
                    'amount' => $wr->amount,
                    'reference' => Transaction::generateReference(),
                    'description' => 'Bank withdrawal (approved by admin)',
                    'status' => $transactionStatus,
                    'metadata' => [
                        'withdraw_request_id' => $wr->id,
                        'original_amount' => $wr->amount,
                        'debited_amount' => $totalDebitAmount,
                        'withdrawal_fee' => $feeAmount,
                        'net_payout_amount' => $netPayoutAmount,
                        'beneficiary_mobile' => data_get($wr->metadata, 'beneficiary_mobile'),
                        'payout' => $payoutMeta,
                        'remarks' => $payload['remarks'] ?? null,
                    ],
                ]);

                $wallet->balance -= $totalDebitAmount;
                $wallet->save();

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

                WalletLimit::updateLimit($wr->user_id, (float) $wr->amount, 'daily');
                WalletLimit::updateLimit($wr->user_id, (float) $wr->amount, 'monthly');

                $wr->status = $withdrawRequestStatus;
                $wr->remarks = $payload['remarks'] ?? $wr->remarks;
                $wr->metadata = array_merge($wr->metadata ?? [], [
                    'payout' => $payoutMeta,
                    'processing_state' => $processingState,
                ]);
                $wr->reviewed_by = auth()->id();
                $wr->reviewed_at = now();
                $wr->save();

                \App\Http\Controllers\Api\RetailerController::notify(
                    $wr->user_id,
                    $notifyType,
                    $notifyTitle,
                    $notifyMessage,
                    [
                        'withdraw_request_id' => $wr->id,
                        'transaction_id' => $tx->id,
                        'amount' => (float) $wr->amount,
                    ]
                );

                $this->logAdminAction('approve_withdraw', 'withdraw_request', $wr->id, ['transaction_id' => $tx->id]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to approve withdrawal: ' . $e->getMessage());
        }

        return back()->with('success', 'Withdraw request approved and sent to payout processing.');
    }

    public function rejectWithdraw(Request $request, int $id)
    {
        $payload = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $wr = WithdrawRequest::findOrFail($id);
        if ($wr->status !== 'pending' && $wr->status !== 'approved') {
            return back()->with('error', 'Only pending/approved requests can be rejected.');
        }

        $wr->status = 'rejected';
        $wr->remarks = $payload['remarks'] ?? $wr->remarks;
        $wr->reviewed_by = auth()->id();
        $wr->reviewed_at = now();
        $wr->save();

        \App\Http\Controllers\Api\RetailerController::notify(
            $wr->user_id,
            'withdraw_rejected',
            'Withdraw Rejected',
            'Your withdrawal request was rejected.',
            [
                'withdraw_request_id' => $wr->id,
                'remarks' => $wr->remarks,
            ]
        );

        $this->logAdminAction('reject_withdraw', 'withdraw_request', $wr->id, []);
        return back()->with('success', 'Withdraw request rejected.');
    }

    public function forceSettlement(Request $request)
    {
        $payload = $request->validate([
            'wallet_id' => 'required|integer|exists:wallets,id',
            'amount' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($payload) {
            $wallet = Wallet::with('user')->lockForUpdate()->findOrFail($payload['wallet_id']);
            $amount = (float) $payload['amount'];
            if ($wallet->balance < $amount) {
                throw new \RuntimeException('Insufficient balance for forced settlement.');
            }

            $wallet->balance -= $amount;
            $wallet->save();

            WalletAdjustment::create([
                'admin_user_id' => auth()->id(),
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'type' => 'force_settlement',
                'amount' => $amount,
                'reference' => WalletAdjustment::generateReference(),
                'remarks' => $payload['remarks'] ?? 'Forced settlement by admin',
            ]);

            $this->logAdminAction('force_settlement', 'wallet', $wallet->id, ['amount' => $amount]);
        });

        return back()->with('success', 'Forced settlement applied.');
    }

    public function transferBetweenWallets(Request $request)
    {
        $payload = $request->validate([
            'from_wallet_id' => 'required|integer|exists:wallets,id|different:to_wallet_id',
            'to_wallet_id' => 'required|integer|exists:wallets,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($payload) {
                $fromWallet = Wallet::with('user')->lockForUpdate()->findOrFail($payload['from_wallet_id']);
                $toWallet = Wallet::with('user')->lockForUpdate()->findOrFail($payload['to_wallet_id']);
                $amount = (float) $payload['amount'];

                if ($fromWallet->is_frozen || $toWallet->is_frozen) {
                    throw new \RuntimeException('Cannot transfer to/from frozen wallets.');
                }

                if ((float) $fromWallet->balance < $amount) {
                    throw new \RuntimeException('Insufficient balance in source wallet.');
                }

                $fromWallet->balance = (float) $fromWallet->balance - $amount;
                $toWallet->balance = (float) $toWallet->balance + $amount;
                $fromWallet->save();
                $toWallet->save();

                $tx = Transaction::create([
                    'user_id' => auth()->id(),
                    'from_wallet_id' => $fromWallet->id,
                    'to_wallet_id' => $toWallet->id,
                    'type' => 'transfer',
                    'amount' => $amount,
                    'reference' => Transaction::generateReference(),
                    'description' => $payload['description'] ?? 'Admin wallet-to-wallet transfer',
                    'status' => 'completed',
                ]);

                $this->logAdminAction('wallet_transfer', 'transaction', $tx->id, [
                    'from_wallet_id' => $fromWallet->id,
                    'to_wallet_id' => $toWallet->id,
                    'amount' => $amount,
                ]);
            });

            return redirect()->route('admin.wallets')->with('success', 'Wallet transfer completed successfully.');
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.wallets')->with('error', $e->getMessage());
        }
    }

    public function updateSecuritySettings(Request $request)
    {
        $payload = $request->validate([
            'security_2fa_enforced' => 'nullable|in:0,1',
            'security_ip_restriction' => 'nullable|string|max:1000',
            'security_rate_limit_per_minute' => 'required|integer|min:10|max:10000',
            'security_min_password_length' => 'required|integer|min:8|max:64',
        ]);

        AdminSetting::setValue('security_2fa_enforced', $payload['security_2fa_enforced'] ?? '0');
        AdminSetting::setValue('security_ip_restriction', $payload['security_ip_restriction'] ?? '');
        AdminSetting::setValue('security_rate_limit_per_minute', $payload['security_rate_limit_per_minute']);
        AdminSetting::setValue('security_min_password_length', $payload['security_min_password_length']);

        $this->logAdminAction('update_security_settings', 'admin_setting', 0, $payload);
        return back()->with('success', 'Security settings saved.');
    }

    public function updateSystemSettings(Request $request)
    {
        $payload = $request->validate([
            'site_name' => 'nullable|string|max:120',
            'support_email' => 'nullable|email|max:120',
            'support_phone' => 'nullable|string|max:30',
            'site_address' => 'nullable|string|max:255',
            'site_timezone' => 'nullable|string|max:80',
            'site_currency' => 'nullable|string|max:10',
            'frontend_enabled' => 'nullable|boolean',
            'min_wallet_balance' => 'nullable|numeric|min:0',
            'max_wallet_balance' => 'nullable|numeric|min:0',
            'daily_transfer_limit' => 'nullable|numeric|min:0',
            'transaction_fee' => 'nullable|numeric|min:0',
            'wallet_freeze_limit' => 'nullable|numeric|min:0',
            'withdraw_min_amount' => 'nullable|numeric|min:0',
            'withdraw_max_per_tx' => 'nullable|numeric|min:0',
            'withdraw_charges' => 'nullable|numeric|min:0',
            'withdraw_processing_time' => 'nullable|string|max:120',
            'otp_expiry_time' => 'nullable|integer|min:1|max:120',
            'login_attempt_limit' => 'nullable|integer|min:1|max:20',
            'password_policy' => 'nullable|string|max:255',
            'gateway_ertitech_username' => 'nullable|string|max:255',
            'gateway_ertitech_password' => 'nullable|string|max:255',
            'gateway_ertitech_merchant_id' => 'nullable|string|max:255',
            'gateway_ertitech_wallet_id' => 'nullable|string|max:255',
            'gateway_ertitech_aes_key' => 'nullable|string|max:255',
            'gateway_ertitech_mode' => 'nullable|in:test,live',
            'gateway_payu_key' => 'nullable|string|max:255',
            'gateway_payu_salt' => 'nullable|string|max:255',
            'gateway_payu_mode' => 'nullable|in:test,live',
            'gateway_razorpay_api_key' => 'nullable|string|max:255',
            'gateway_razorpay_secret_key' => 'nullable|string|max:255',
            'gateway_razorpay_webhook_url' => 'nullable|string|max:255',
            'gateway_razorpay_mode' => 'nullable|in:test,live',
            'gateway_paytm_api_key' => 'nullable|string|max:255',
            'gateway_paytm_secret_key' => 'nullable|string|max:255',
            'gateway_paytm_webhook_url' => 'nullable|string|max:255',
            'gateway_paytm_mode' => 'nullable|in:test,live',
            'gateway_stripe_api_key' => 'nullable|string|max:255',
            'gateway_stripe_secret_key' => 'nullable|string|max:255',
            'gateway_stripe_webhook_url' => 'nullable|string|max:255',
            'gateway_stripe_mode' => 'nullable|in:test,live',
            'gateway_recharge_provider' => 'nullable|string|max:120',
            'gateway_recharge_api_key' => 'nullable|string|max:255',
            'gateway_recharge_secret_key' => 'nullable|string|max:255',
            'gateway_recharge_working_key' => 'nullable|string|max:255',
            'gateway_recharge_iv' => 'nullable|string|max:255',
            'gateway_recharge_username' => 'nullable|string|max:255',
            'gateway_recharge_password' => 'nullable|string|max:255',
            'gateway_recharge_auth_base_url' => 'nullable|string|max:255',
            'gateway_recharge_grant_type' => 'nullable|string|max:255',
            'gateway_recharge_scope' => 'nullable|string|max:500',
            'gateway_recharge_agent_id' => 'nullable|string|max:255',
            'gateway_recharge_base_url' => 'nullable|string|max:255',
            'gateway_recharge_mode' => 'nullable|in:test,live',
            'smtp_host' => 'nullable|string|max:120',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:120',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|in:none,ssl,tls',
            'sms_provider' => 'nullable|in:twilio,msg91,fast2sms,none',
            'sms_api_key' => 'nullable|string|max:255',
            'sms_sender_id' => 'nullable|string|max:60',
            'sms_template_id' => 'nullable|string|max:100',
            'maintenance_message' => 'nullable|string|max:255',
            'maintenance_start_time' => 'nullable|date',
            'backup_schedule' => 'nullable|in:manual,daily,weekly',
            'default_currency_code' => 'nullable|string|max:10',
            'default_currency_symbol' => 'nullable|string|max:10',
            'language_default' => 'nullable|in:english,hindi,telugu',
            'deposit_commission_admin' => 'nullable|numeric|min:0|max:100',
            'deposit_commission_master_distributor' => 'nullable|numeric|min:0|max:100',
            'deposit_commission_super_distributor' => 'nullable|numeric|min:0|max:100',
            'deposit_commission_distributor' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($request->filled('gateway_razorpay_secret_key') && preg_match('/^[*xX]+$/', (string) $request->input('gateway_razorpay_secret_key')) === 1) {
            return back()
                ->withInput()
                ->withErrors(['gateway_razorpay_secret_key' => 'Enter the real Razorpay Key Secret. Masked placeholder characters are not valid credentials.']);
        }

        if ($request->filled('gateway_payu_salt') && preg_match('/^[*xX]+$/', (string) $request->input('gateway_payu_salt')) === 1) {
            return back()
                ->withInput()
                ->withErrors(['gateway_payu_salt' => 'Enter the real PayU Merchant Salt. Masked placeholder characters are not valid credentials.']);
        }

        $booleanKeys = [
            'frontend_enabled',
            'withdraw_approval_required',
            'enable_2fa',
            'ip_blocking',
            'email_notifications',
            'sms_notifications',
            'admin_alerts',
            'push_notifications',
            'maintenance_mode',
            'auto_daily_backup',
        ];

        foreach ($payload as $key => $value) {
            AdminSetting::setValue('sys_' . $key, $value ?? '');
        }
        if (array_key_exists('withdraw_min_amount', $payload)) {
            AdminSetting::setValue('withdraw_min_amount', $payload['withdraw_min_amount'] ?? '100');
        }
        if (array_key_exists('withdraw_max_per_tx', $payload)) {
            AdminSetting::setValue('withdraw_max_per_tx', $payload['withdraw_max_per_tx'] ?? '500000');
        }
        foreach ($booleanKeys as $key) {
            if ($request->exists($key)) {
                AdminSetting::setValue('sys_' . $key, $request->boolean($key) ? '1' : '0');
            }
        }

        $this->syncGatewaySettingsToEnv($payload);

        $this->logAdminAction('update_system_settings', 'admin_setting', 0, [
            'updated_keys' => array_keys($payload),
            'boolean_keys' => $booleanKeys,
        ]);

        $section = (string) $request->input('section', 'general');
        $successMessage = $section === 'frontend-server'
            ? ($request->boolean('frontend_enabled') ? 'frontend server is on' : 'frontend server is off')
            : 'System settings saved successfully.';

        return redirect()->route('admin.settings', ['section' => $section])
            ->with('success', $successMessage);
    }

    public function testErtitechConnection(Request $request)
    {
        $payload = $request->validate([
            'gateway_ertitech_username' => 'required|string|max:255',
            'gateway_ertitech_password' => 'required|string|max:255',
            'gateway_ertitech_merchant_id' => 'required|string|max:255',
            'gateway_ertitech_wallet_id' => 'nullable|string|max:255',
            'gateway_ertitech_aes_key' => 'required|string|max:255',
            'gateway_ertitech_mode' => 'required|in:test,live',
        ]);

        $baseUrl = $payload['gateway_ertitech_mode'] === 'live'
            ? 'https://api.ertipay.com/payout'
            : 'https://api.ertipay.com/uat';

        try {
            $result = (new ErtitechPayoutService([
                'base_url' => $baseUrl,
                'username' => $payload['gateway_ertitech_username'],
                'password' => $payload['gateway_ertitech_password'],
                'merchant_id' => $payload['gateway_ertitech_merchant_id'],
                'wallet_id' => $payload['gateway_ertitech_wallet_id'] ?? '',
                'aes_key' => $payload['gateway_ertitech_aes_key'],
                'preferred_bank' => 'pnb',
                'mode' => $payload['gateway_ertitech_mode'],
            ]))->testConnection();

            $this->logAdminAction('test_ertitech_connection', 'admin_setting', 0, [
                'mode' => $result['mode'],
                'base_url' => $result['base_url'],
                'merchant_id' => $result['merchant_id'],
            ]);

            return redirect()->route('admin.settings', ['section' => 'payment-gateway'])
                ->with('success', $result['message'] . ' Mode: ' . strtoupper($result['mode']) . ', URL: ' . $result['base_url']);
        } catch (\Throwable $e) {
            return redirect()->route('admin.settings', ['section' => 'payment-gateway'])
                ->withInput()
                ->with('error', 'Ertitech connection failed: ' . $e->getMessage());
        }
    }

    public function exportTransactionsCsv()
    {
        $filename = 'admin_transactions_' . now()->format('Ymd_His') . '.csv';

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['section', 'date', 'user', 'type', 'amount', 'status', 'reference', 'details']);

            Transaction::with('user')->orderBy('created_at', 'desc')->limit(1000)->get()->each(function ($row) use ($handle) {
                fputcsv($handle, [
                    'transaction',
                    optional($row->created_at)->format('Y-m-d H:i:s'),
                    $row->user?->email,
                    $row->type,
                    (float) $row->amount,
                    $row->status,
                    $row->reference,
                    $row->description,
                ]);
            });

            CommissionTransaction::with('user')->orderBy('created_at', 'desc')->limit(1000)->get()->each(function ($row) use ($handle) {
                fputcsv($handle, [
                    'commission',
                    optional($row->created_at)->format('Y-m-d H:i:s'),
                    $row->user?->email,
                    $row->commission_type,
                    (float) $row->commission_amount,
                    'completed',
                    $row->reference,
                    $row->description,
                ]);
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function syncGatewaySettingsToEnv(array $payload): void
    {
        $envUpdates = [];

        if (array_key_exists('gateway_ertitech_username', $payload)) {
            $envUpdates['ERTITECH_PAYOUT_USERNAME'] = (string) ($payload['gateway_ertitech_username'] ?? '');
        }

        if (array_key_exists('gateway_ertitech_password', $payload)) {
            $envUpdates['ERTITECH_PAYOUT_PASSWORD'] = (string) ($payload['gateway_ertitech_password'] ?? '');
        }

        if (array_key_exists('gateway_ertitech_merchant_id', $payload)) {
            $envUpdates['ERTITECH_PAYOUT_MERCHANT_ID'] = (string) ($payload['gateway_ertitech_merchant_id'] ?? '');
        }

        if (array_key_exists('gateway_ertitech_wallet_id', $payload)) {
            $envUpdates['ERTITECH_PAYOUT_WALLET_ID'] = (string) ($payload['gateway_ertitech_wallet_id'] ?? '');
        }

        if (array_key_exists('gateway_ertitech_aes_key', $payload)) {
            $envUpdates['ERTITECH_PAYOUT_AES_KEY'] = (string) ($payload['gateway_ertitech_aes_key'] ?? '');
        }

        if (array_key_exists('gateway_ertitech_mode', $payload)) {
            $ertitechMode = (string) ($payload['gateway_ertitech_mode'] ?? 'test');
            $envUpdates['ERTITECH_PAYOUT_MODE'] = $ertitechMode;
            $envUpdates['ERTITECH_PAYOUT_BASE_URL'] = $ertitechMode === 'live'
                ? 'https://api.ertipay.com/payout'
                : 'https://api.ertipay.com/uat';
        }

        $envUpdates['ERTITECH_PAYOUT_PREFERRED_BANK'] = 'pnb';

        if (array_key_exists('gateway_payu_key', $payload)) {
            $envUpdates['PAYU_MERCHANT_KEY'] = (string) ($payload['gateway_payu_key'] ?? '');
        }

        if (array_key_exists('gateway_payu_salt', $payload)) {
            $envUpdates['PAYU_MERCHANT_SALT'] = (string) ($payload['gateway_payu_salt'] ?? '');
        }

        if (array_key_exists('gateway_payu_mode', $payload)) {
            $envUpdates['PAYU_MODE'] = (string) ($payload['gateway_payu_mode'] ?? 'test');
        }

        if (array_key_exists('gateway_razorpay_api_key', $payload)) {
            $envUpdates['RAZORPAY_KEY_ID'] = (string) ($payload['gateway_razorpay_api_key'] ?? '');
        }

        if (array_key_exists('gateway_razorpay_secret_key', $payload)) {
            $envUpdates['RAZORPAY_KEY_SECRET'] = (string) ($payload['gateway_razorpay_secret_key'] ?? '');
        }

        if (array_key_exists('gateway_razorpay_mode', $payload)) {
            $envUpdates['RAZORPAY_MODE'] = (string) ($payload['gateway_razorpay_mode'] ?? 'test');
        }

        if (array_key_exists('gateway_recharge_provider', $payload)) {
            $envUpdates['RETAILER_RECHARGE_PROVIDER'] = (string) ($payload['gateway_recharge_provider'] ?? '');
        }

        if (array_key_exists('gateway_recharge_api_key', $payload)) {
            $envUpdates['RETAILER_RECHARGE_API_KEY'] = (string) ($payload['gateway_recharge_api_key'] ?? '');
        }

        if (array_key_exists('gateway_recharge_secret_key', $payload)) {
            $envUpdates['RETAILER_RECHARGE_SECRET_KEY'] = (string) ($payload['gateway_recharge_secret_key'] ?? '');
        }

        if (array_key_exists('gateway_recharge_working_key', $payload)) {
            $envUpdates['RETAILER_RECHARGE_WORKING_KEY'] = (string) ($payload['gateway_recharge_working_key'] ?? '');
        }

        if (array_key_exists('gateway_recharge_iv', $payload)) {
            $envUpdates['RETAILER_RECHARGE_IV'] = (string) ($payload['gateway_recharge_iv'] ?? '');
        }

        if (array_key_exists('gateway_recharge_username', $payload)) {
            $envUpdates['RETAILER_RECHARGE_USERNAME'] = (string) ($payload['gateway_recharge_username'] ?? '');
        }

        if (array_key_exists('gateway_recharge_password', $payload)) {
            $envUpdates['RETAILER_RECHARGE_PASSWORD'] = (string) ($payload['gateway_recharge_password'] ?? '');
        }

        if (array_key_exists('gateway_recharge_auth_base_url', $payload)) {
            $envUpdates['RETAILER_RECHARGE_AUTH_BASE_URL'] = (string) ($payload['gateway_recharge_auth_base_url'] ?? '');
        }

        if (array_key_exists('gateway_recharge_grant_type', $payload)) {
            $envUpdates['RETAILER_RECHARGE_GRANT_TYPE'] = (string) ($payload['gateway_recharge_grant_type'] ?? 'client_credentials');
        }

        if (array_key_exists('gateway_recharge_scope', $payload)) {
            $envUpdates['RETAILER_RECHARGE_SCOPE'] = (string) ($payload['gateway_recharge_scope'] ?? '');
        }

        if (array_key_exists('gateway_recharge_agent_id', $payload)) {
            $envUpdates['RETAILER_RECHARGE_AGENT_ID'] = (string) ($payload['gateway_recharge_agent_id'] ?? '');
        }

        if (array_key_exists('gateway_recharge_base_url', $payload)) {
            $envUpdates['RETAILER_RECHARGE_BASE_URL'] = (string) ($payload['gateway_recharge_base_url'] ?? '');
        }

        if (array_key_exists('gateway_recharge_mode', $payload)) {
            $envUpdates['RETAILER_RECHARGE_MODE'] = (string) ($payload['gateway_recharge_mode'] ?? 'test');
        }

        if ($envUpdates === []) {
            return;
        }

        $envPath = base_path('.env');
        if (!is_file($envPath) || !is_readable($envPath) || !is_writable($envPath)) {
            throw new RuntimeException('The .env file is not accessible for gateway credential sync.');
        }

        $envContents = file_get_contents($envPath);
        if ($envContents === false) {
            throw new RuntimeException('Unable to read the .env file for gateway credential sync.');
        }

        foreach ($envUpdates as $key => $value) {
            $envContents = $this->upsertEnvValue($envContents, $key, $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }

        if (file_put_contents($envPath, $envContents) === false) {
            throw new RuntimeException('Unable to update the .env file for gateway credential sync.');
        }
    }

    private function upsertEnvValue(string $envContents, string $key, string $value): string
    {
        $escapedKey = preg_quote($key, '/');
        $formattedLine = $key . '=' . $this->formatEnvValue($value);

        if (preg_match('/^' . $escapedKey . '=.*$/m', $envContents) === 1) {
            return (string) preg_replace('/^' . $escapedKey . '=.*$/m', $formattedLine, $envContents, 1);
        }

        $suffix = str_ends_with($envContents, PHP_EOL) ? '' : PHP_EOL;

        return $envContents . $suffix . $formattedLine . PHP_EOL;
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return '"' . addcslashes($value, "\\\"\n\r\t") . '"';
    }

    private function baseData(): array
    {
        $admin = auth()->user();
        // Use the system admin main wallet consistently with withdrawal fee credits.
        $adminMainWallet = Wallet::whereHas('user', function ($query) {
                $query->where('role', 'admin');
            })
            ->where('type', 'main')
            ->orderBy('id')
            ->first();

        $stats = [
            'admin_main_wallet_balance' => (float) ($adminMainWallet?->balance ?? 0),
            // Keep network float separate from admin holdings: exclude admin wallets.
            'total_wallet_balance' => (float) Wallet::whereHas('user', function ($query) {
                $query->where('role', '!=', 'admin');
            })->sum('balance'),
            'total_master_distributors' => User::where('role', 'master_distributor')->count(),
            'total_super_distributors' => User::where('role', 'super_distributor')->count(),
            'total_distributors' => User::where('role', 'distributor')->count(),
            'total_retailers' => User::where('role', 'retailer')->count(),
            'total_commission_paid' => (float) CommissionTransaction::sum('commission_amount'),
            'total_withdrawals' => (float) Transaction::where('type', 'withdraw')->sum('amount'),
            'total_withdraw_today' => (float) Transaction::where('type', 'withdraw')->whereDate('created_at', now()->toDateString())->sum('amount'),
            'total_commission_today' => (float) CommissionTransaction::whereDate('created_at', now()->toDateString())->sum('commission_amount'),
            'active_users_count' => User::where('is_active', true)->count(),
        ];

        $masterDistributors = User::where('role', 'master_distributor')->with('wallets')->orderBy('id')->get();
        $superDistributors = User::where('role', 'super_distributor')->with('wallets')->orderBy('id')->get();
        $distributors = User::where('role', 'distributor')->with('wallets')->orderBy('id')->get();
        $retailers = User::where('role', 'retailer')->with(['wallets', 'distributor'])->orderBy('id')->get();
        $allWallets = Wallet::with('user')->orderBy('id')->get();
        $allNonAdminUsers = User::whereIn('role', ['master_distributor', 'super_distributor', 'distributor', 'retailer', 'user'])->orderBy('name')->get();

        $recentWithdrawals = Transaction::with(['user', 'fromWallet'])
            ->where('type', 'withdraw')->orderBy('created_at', 'desc')->limit(20)->get();
        $recentCommissions = CommissionTransaction::with(['user', 'wallet'])
            ->orderBy('created_at', 'desc')->limit(20)->get();
        $withdrawRequests = WithdrawRequest::with(['user', 'wallet', 'reviewer'])
            ->orderBy('created_at', 'desc')->limit(100)->get();
        $withdrawChargeTransactions = Transaction::with(['user', 'fromWallet'])
            ->where('type', 'withdraw')
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->filter(function ($transaction) {
                $fee = (float) data_get($transaction->metadata, 'withdrawal_fee', 0);
                $role = (string) ($transaction->user?->role ?? '');
                return $fee > 0 && in_array($role, ['retailer', 'user'], true);
            })
            ->values();
        $walletAdjustments = WalletAdjustment::with(['admin', 'user', 'wallet'])
            ->orderBy('created_at', 'desc')->limit(100)->get();
        $adminLogs = AdminActionLog::with('admin')->orderBy('created_at', 'desc')->limit(200)->get();
        $commissionOverrides = CommissionOverride::with('user')->orderBy('id')->get();
        $commissionConfigs = CommissionConfig::where('is_active', true)->orderBy('user_role')->get();
        $adminNotifications = $admin
            ? $admin->notifications()->orderByDesc('created_at')->limit(20)->get()
            : collect();
        $adminUnreadNotifications = $adminNotifications->where('is_read', false)->count();

        $security = [
            'security_2fa_enforced' => AdminSetting::getValue('security_2fa_enforced', '0'),
            'security_ip_restriction' => AdminSetting::getValue('security_ip_restriction', ''),
            'security_rate_limit_per_minute' => AdminSetting::getValue('security_rate_limit_per_minute', '120'),
            'security_min_password_length' => AdminSetting::getValue('security_min_password_length', '8'),
        ];
        $withdrawConfig = [
            'withdraw_approval_mode' => AdminSetting::getValue('withdraw_approval_mode', 'auto'),
            'withdraw_min_amount' => AdminSetting::getValue('withdraw_min_amount', '100'),
            'withdraw_max_per_tx' => AdminSetting::getValue('withdraw_max_per_tx', '500000'),
        ];
        $systemSettings = [
            'site_name' => AdminSetting::getValue('sys_site_name', 'Wallet Admin'),
            'support_email' => AdminSetting::getValue('sys_support_email', ''),
            'support_phone' => AdminSetting::getValue('sys_support_phone', ''),
            'site_address' => AdminSetting::getValue('sys_site_address', ''),
            'site_timezone' => AdminSetting::getValue('sys_site_timezone', 'Asia/Kolkata'),
            'site_currency' => AdminSetting::getValue('sys_site_currency', 'INR'),
            'frontend_enabled' => AdminSetting::getValue('sys_frontend_enabled', '1'),
            'min_wallet_balance' => AdminSetting::getValue('sys_min_wallet_balance', '0'),
            'max_wallet_balance' => AdminSetting::getValue('sys_max_wallet_balance', '1000000'),
            'daily_transfer_limit' => AdminSetting::getValue('sys_daily_transfer_limit', '50000'),
            'transaction_fee' => AdminSetting::getValue('sys_transaction_fee', '0'),
            'wallet_freeze_limit' => AdminSetting::getValue('sys_wallet_freeze_limit', '100000'),
            'withdraw_charges' => AdminSetting::getValue('sys_withdraw_charges', '0'),
            'withdraw_approval_required' => AdminSetting::getValue('sys_withdraw_approval_required', '1'),
            'withdraw_processing_time' => AdminSetting::getValue('sys_withdraw_processing_time', '24-48 hours'),
            'enable_2fa' => AdminSetting::getValue('sys_enable_2fa', '0'),
            'login_attempt_limit' => AdminSetting::getValue('sys_login_attempt_limit', '5'),
            'otp_expiry_time' => AdminSetting::getValue('sys_otp_expiry_time', '5'),
            'ip_blocking' => AdminSetting::getValue('sys_ip_blocking', '1'),
            'password_policy' => AdminSetting::getValue('sys_password_policy', 'Min 8 chars, uppercase, lowercase, number'),
            'email_notifications' => AdminSetting::getValue('sys_email_notifications', '1'),
            'sms_notifications' => AdminSetting::getValue('sys_sms_notifications', '0'),
            'admin_alerts' => AdminSetting::getValue('sys_admin_alerts', '1'),
            'push_notifications' => AdminSetting::getValue('sys_push_notifications', '0'),
            'gateway_ertitech_username' => AdminSetting::getFirstValue(['gateway_ertitech_username', 'sys_gateway_ertitech_username'], ''),
            'gateway_ertitech_password' => AdminSetting::getFirstValue(['gateway_ertitech_password', 'sys_gateway_ertitech_password'], ''),
            'gateway_ertitech_merchant_id' => AdminSetting::getFirstValue(['gateway_ertitech_merchant_id', 'sys_gateway_ertitech_merchant_id'], ''),
            'gateway_ertitech_wallet_id' => AdminSetting::getFirstValue(['gateway_ertitech_wallet_id', 'sys_gateway_ertitech_wallet_id'], ''),
            'gateway_ertitech_aes_key' => AdminSetting::getFirstValue(['gateway_ertitech_aes_key', 'sys_gateway_ertitech_aes_key'], ''),
            'gateway_ertitech_mode' => AdminSetting::getFirstValue(['gateway_ertitech_mode', 'sys_gateway_ertitech_mode'], 'test'),
            'gateway_payu_key' => AdminSetting::getValue('sys_gateway_payu_key', ''),
            'gateway_payu_salt' => AdminSetting::getValue('sys_gateway_payu_salt', ''),
            'gateway_payu_mode' => AdminSetting::getValue('sys_gateway_payu_mode', 'test'),
            'gateway_razorpay_api_key' => AdminSetting::getValue('sys_gateway_razorpay_api_key', ''),
            'gateway_razorpay_secret_key' => AdminSetting::getValue('sys_gateway_razorpay_secret_key', ''),
            'gateway_razorpay_webhook_url' => AdminSetting::getValue('sys_gateway_razorpay_webhook_url', ''),
            'gateway_razorpay_mode' => AdminSetting::getValue('sys_gateway_razorpay_mode', 'test'),
            'gateway_paytm_api_key' => AdminSetting::getValue('sys_gateway_paytm_api_key', ''),
            'gateway_paytm_secret_key' => AdminSetting::getValue('sys_gateway_paytm_secret_key', ''),
            'gateway_paytm_webhook_url' => AdminSetting::getValue('sys_gateway_paytm_webhook_url', ''),
            'gateway_paytm_mode' => AdminSetting::getValue('sys_gateway_paytm_mode', 'test'),
            'gateway_stripe_api_key' => AdminSetting::getValue('sys_gateway_stripe_api_key', ''),
            'gateway_stripe_secret_key' => AdminSetting::getValue('sys_gateway_stripe_secret_key', ''),
            'gateway_stripe_webhook_url' => AdminSetting::getValue('sys_gateway_stripe_webhook_url', ''),
            'gateway_stripe_mode' => AdminSetting::getValue('sys_gateway_stripe_mode', 'test'),
            'gateway_recharge_provider' => AdminSetting::getValue('sys_gateway_recharge_provider', ''),
            'gateway_recharge_api_key' => AdminSetting::getValue('sys_gateway_recharge_api_key', ''),
            'gateway_recharge_secret_key' => AdminSetting::getValue('sys_gateway_recharge_secret_key', ''),
            'gateway_recharge_working_key' => AdminSetting::getValue('sys_gateway_recharge_working_key', ''),
            'gateway_recharge_iv' => AdminSetting::getValue('sys_gateway_recharge_iv', ''),
            'gateway_recharge_username' => AdminSetting::getValue('sys_gateway_recharge_username', ''),
            'gateway_recharge_password' => AdminSetting::getValue('sys_gateway_recharge_password', ''),
            'gateway_recharge_auth_base_url' => AdminSetting::getValue('sys_gateway_recharge_auth_base_url', ''),
            'gateway_recharge_grant_type' => AdminSetting::getValue('sys_gateway_recharge_grant_type', 'client_credentials'),
            'gateway_recharge_scope' => AdminSetting::getValue('sys_gateway_recharge_scope', ''),
            'gateway_recharge_agent_id' => AdminSetting::getValue('sys_gateway_recharge_agent_id', ''),
            'gateway_recharge_base_url' => AdminSetting::getValue('sys_gateway_recharge_base_url', ''),
            'gateway_recharge_mode' => AdminSetting::getValue('sys_gateway_recharge_mode', 'test'),
            'smtp_host' => AdminSetting::getValue('sys_smtp_host', ''),
            'smtp_port' => AdminSetting::getValue('sys_smtp_port', '587'),
            'smtp_username' => AdminSetting::getValue('sys_smtp_username', ''),
            'smtp_password' => AdminSetting::getValue('sys_smtp_password', ''),
            'smtp_encryption' => AdminSetting::getValue('sys_smtp_encryption', 'tls'),
            'sms_provider' => AdminSetting::getValue('sys_sms_provider', 'none'),
            'sms_api_key' => AdminSetting::getValue('sys_sms_api_key', ''),
            'sms_sender_id' => AdminSetting::getValue('sys_sms_sender_id', ''),
            'sms_template_id' => AdminSetting::getValue('sys_sms_template_id', ''),
            'maintenance_mode' => AdminSetting::getValue('sys_maintenance_mode', '0'),
            'maintenance_message' => AdminSetting::getValue('sys_maintenance_message', 'System under maintenance. Please check back soon.'),
            'maintenance_start_time' => AdminSetting::getValue('sys_maintenance_start_time', ''),
            'backup_schedule' => AdminSetting::getValue('sys_backup_schedule', 'daily'),
            'auto_daily_backup' => AdminSetting::getValue('sys_auto_daily_backup', '1'),
            'default_currency_code' => AdminSetting::getValue('sys_default_currency_code', 'INR'),
            'default_currency_symbol' => AdminSetting::getValue('sys_default_currency_symbol', '₹'),
            'language_default' => AdminSetting::getValue('sys_language_default', 'english'),
            'deposit_commission_admin' => AdminSetting::getValue('sys_deposit_commission_admin', '0.02'),
            'deposit_commission_master_distributor' => AdminSetting::getValue('sys_deposit_commission_master_distributor', '0.02'),
            'deposit_commission_super_distributor' => AdminSetting::getValue('sys_deposit_commission_super_distributor', '0.02'),
            'deposit_commission_distributor' => AdminSetting::getValue('sys_deposit_commission_distributor', '0.02'),
        ];

        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->startOfMonth()->subMonths($i);
            $key = $monthStart->format('M Y');
            $monthlyRevenue[$key] = (float) CommissionTransaction::whereBetween('created_at', [
                $monthStart->copy()->startOfMonth(),
                $monthStart->copy()->endOfMonth(),
            ])->sum('commission_amount');
        }

        $dailyRangeDays = 10;
        $dailyStart = now()->copy()->subDays($dailyRangeDays - 1)->startOfDay();
        $dailyEnd = now()->copy()->endOfDay();

        $dailyDeposits = Transaction::query()
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->where('type', 'deposit')
            ->whereBetween('created_at', [$dailyStart, $dailyEnd])
            ->groupBy('day')
            ->pluck('total', 'day');

        $dailyWithdrawals = Transaction::query()
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->where('type', 'withdraw')
            ->whereBetween('created_at', [$dailyStart, $dailyEnd])
            ->groupBy('day')
            ->pluck('total', 'day');

        $dailyDepositWithdrawSeries = [];
        for ($i = $dailyRangeDays - 1; $i >= 0; $i--) {
            $day = now()->copy()->subDays($i);
            $key = $day->toDateString();
            $dailyDepositWithdrawSeries[] = [
                'date' => $key,
                'label' => $day->format('d M'),
                'deposit' => (float) ($dailyDeposits[$key] ?? 0),
                'withdrawal' => (float) ($dailyWithdrawals[$key] ?? 0),
            ];
        }

        $todayKey = now()->toDateString();
        $todayDeposit = (float) ($dailyDeposits[$todayKey] ?? 0);
        $todayWithdrawal = (float) ($dailyWithdrawals[$todayKey] ?? 0);
        $dailyTrendSummary = [
            'today_deposit' => $todayDeposit,
            'today_withdrawal' => $todayWithdrawal,
            'today_net' => $todayDeposit - $todayWithdrawal,
        ];

        return [
            'stats' => $stats,
            'admin' => $admin,
            'adminMainWallet' => $adminMainWallet,
            'masterDistributors' => $masterDistributors,
            'superDistributors' => $superDistributors,
            'distributors' => $distributors,
            'retailers' => $retailers,
            'allWallets' => $allWallets,
            'allNonAdminUsers' => $allNonAdminUsers,
            'recentWithdrawals' => $recentWithdrawals,
            'recentCommissions' => $recentCommissions,
            'withdrawRequests' => $withdrawRequests,
            'withdrawChargeTransactions' => $withdrawChargeTransactions,
            'walletAdjustments' => $walletAdjustments,
            'adminLogs' => $adminLogs,
            'commissionOverrides' => $commissionOverrides,
            'commissionConfigs' => $commissionConfigs,
            'adminNotifications' => $adminNotifications,
            'adminUnreadNotifications' => $adminUnreadNotifications,
            'security' => $security,
            'withdrawConfig' => $withdrawConfig,
            'systemSettings' => $systemSettings,
            'monthlyRevenue' => $monthlyRevenue,
            'dailyDepositWithdrawSeries' => $dailyDepositWithdrawSeries,
            'dailyTrendSummary' => $dailyTrendSummary,
            'search' => '',
            'typeFilter' => null,
        ];
    }

    private function logAdminAction(string $action, string $targetType, int $targetId, array $metadata): void
    {
        AdminActionLog::create([
            'admin_user_id' => auth()->id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }

}
