<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\CommissionConfig;
use App\Models\CommissionTransaction;
use App\Models\Transaction;
use App\Models\UserNotification;
use App\Models\WithdrawRequest;
use App\Services\BillAvenueBbpsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class RetailerController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $walletBalance = (float) $user->wallets()->sum('balance');
        $minWithdraw = (float) AdminSetting::getValue('withdraw_min_amount', 100);
        $availableWithdraw = max(0, $walletBalance);
        $commission = CommissionConfig::calculateForUser($user, 100.0);

        return response()->json([
            'wallet_balance' => $walletBalance,
            'available_withdraw_amount' => $availableWithdraw,
            'min_withdraw_amount' => $minWithdraw,
            'recharge_gateway' => app(BillAvenueBbpsService::class)->connectionSummary(),
            'commission_breakdown' => [
                'admin_commission_percentage' => (float) ($commission['admin_commission_percentage'] ?? 0),
                'distributor_commission_percentage' => (float) ($commission['distributor_commission_percentage'] ?? 0),
                'retailer_receives_percentage' => round(
                    100 - (float) ($commission['admin_commission_percentage'] ?? 0) - (float) ($commission['distributor_commission_percentage'] ?? 0),
                    2
                ),
            ],
            'withdraw_requests_pending' => WithdrawRequest::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->count(),
        ]);
    }

    public function withdrawRequests(Request $request)
    {
        $requests = WithdrawRequest::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get();

        return response()->json($requests);
    }

    public function notifications(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get();

        return response()->json($notifications);
    }

    public function markNotificationRead(Request $request, int $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->is_read = true;
        $notification->save();

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function updateProfile(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
        ]);

        $user = $request->user();
        $user->name = $payload['name'];
        $user->phone = $payload['phone'] ?? null;
        $user->date_of_birth = $payload['date_of_birth'] ?? null;
        $user->save();

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }

    public function updateBankDetails(Request $request)
    {
        $payload = $request->validate([
            'bank_account_name' => 'required|string|max:255',
            'bank_account_number' => 'required|string|max:40',
            'bank_ifsc_code' => 'required|string|max:32',
            'bank_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $user->bank_account_name = $payload['bank_account_name'];
        $user->bank_account_number = $payload['bank_account_number'];
        $user->bank_ifsc_code = strtoupper($payload['bank_ifsc_code']);
        $user->bank_name = $payload['bank_name'] ?? null;
        $user->save();

        return response()->json(['message' => 'Bank details updated successfully']);
    }

    public function changePassword(Request $request)
    {
        $payload = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();
        if (!Hash::check($payload['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->password = Hash::make($payload['new_password']);
        $user->plain_password = null;
        $user->save();

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function uploadKyc(Request $request)
    {
        $payload = $request->validate([
            'kyc_document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $path = $payload['kyc_document']->store('kyc_documents', 'public');
        $user = $request->user();
        $user->kyc_document_path = $path;
        $user->kyc_status = 'submitted';
        $user->save();

        return response()->json([
            'message' => 'KYC document uploaded successfully',
            'kyc_document_path' => $path,
            'kyc_status' => $user->kyc_status,
        ]);
    }

    public function submitEkyc(Request $request)
    {
        $user = $request->user();

        $payload = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'date_of_birth' => 'nullable|date|before:today',
            'document_type' => 'required|in:aadhaar,pan',
            'kyc_id_number' => 'required|string|max:64',
            'profile_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'document_front' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'document_back' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'selfie_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'liveness_verified' => 'nullable|boolean',
        ]);

        $profilePhotoPath = $request->hasFile('profile_photo')
            ? $request->file('profile_photo')->store('users/profile-photo', 'public')
            : $user->profile_photo_path;
        $documentFrontPath = $request->file('document_front')->store('users/kyc/front', 'public');
        $documentBackPath = $request->file('document_back')->store('users/kyc/back', 'public');
        $selfiePath = $request->hasFile('selfie_photo')
            ? $request->file('selfie_photo')->store('users/kyc/selfie', 'public')
            : $user->kyc_selfie_path;

        $user->name = $payload['first_name'];
        $user->last_name = $payload['last_name'] ?? null;
        $user->email = $payload['email'];
        $user->date_of_birth = $payload['date_of_birth'] ?? null;
        $user->profile_photo_path = $profilePhotoPath;
        $user->kyc_document_type = $payload['document_type'];
        $user->kyc_id_number = $payload['kyc_id_number'];
        $user->kyc_photo_path = $documentFrontPath;
        $user->address_proof_front_path = $documentFrontPath;
        $user->address_proof_back_path = $documentBackPath;
        $user->kyc_document_path = $documentFrontPath;
        $user->kyc_selfie_path = $selfiePath;
        $user->kyc_liveness_verified = (bool) ($payload['liveness_verified'] ?? false);
        $user->kyc_status = 'pending';
        $user->save();

        return response()->json([
            'message' => 'eKYC submitted successfully. Status is pending approval.',
            'kyc_status' => $user->kyc_status,
        ]);
    }

    public function statementExport(Request $request)
    {
        $payload = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'nullable|in:deposit,withdraw,transfer,recharge,commission',
        ]);

        $startDate = $payload['start_date'] ?? null;
        $endDate = $payload['end_date'] ?? null;
        $type = $payload['type'] ?? null;

        $transactions = Transaction::where('user_id', $request->user()->id)
            ->when($type && $type !== 'commission', fn($q) => $q->where('type', $type))
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
            ->orderBy('created_at', 'desc')
            ->get();

        $commissions = CommissionTransaction::where('user_id', $request->user()->id)
            ->when($type && $type !== 'commission', fn($q) => $q->whereRaw('1 = 0'))
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'retailer_statement_' . now()->format('Ymd_His') . '.csv';

        return response()->stream(function () use ($transactions, $commissions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['date', 'section', 'type', 'amount', 'status', 'reference', 'details']);

            foreach ($transactions as $row) {
                fputcsv($handle, [
                    optional($row->created_at)->format('Y-m-d H:i:s'),
                    'transaction',
                    $row->type,
                    (float) $row->amount,
                    $row->status,
                    $row->reference,
                    $row->description,
                ]);
            }

            foreach ($commissions as $row) {
                fputcsv($handle, [
                    optional($row->created_at)->format('Y-m-d H:i:s'),
                    'commission',
                    $row->commission_type,
                    (float) $row->commission_amount,
                    'completed',
                    $row->reference,
                    $row->description,
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public static function notify(int $userId, string $type, string $title, string $message, array $metadata = []): void
    {
        UserNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }
}
