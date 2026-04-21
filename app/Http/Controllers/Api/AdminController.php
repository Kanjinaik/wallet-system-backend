<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function users(Request $request)
    {
        $users = User::withCount(['wallets', 'transactions'])
            ->get();
        return response()->json($users);
    }

    public function wallets(Request $request)
    {
        $wallets = Wallet::with('user')->get();
        return response()->json($wallets);
    }

    public function transactions(Request $request)
    {
        $transactions = Transaction::with(['user', 'fromWallet', 'toWallet'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json($transactions);
    }

    public function transfer(Request $request)
    {
        $payload = $request->validate([
            'from_wallet_id' => 'required|exists:wallets,id',
            'to_wallet_id' => 'required|exists:wallets,id|different:from_wallet_id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
        ]);

        $fromWallet = Wallet::findOrFail($payload['from_wallet_id']);
        $toWallet = Wallet::findOrFail($payload['to_wallet_id']);

        if ($fromWallet->is_frozen || $toWallet->is_frozen) {
            return response()->json(['message' => 'Cannot transfer to/from frozen wallets'], 422);
        }

        if ((float) $fromWallet->balance < (float) $payload['amount']) {
            return response()->json(['message' => 'Insufficient balance in source wallet'], 422);
        }

        return DB::transaction(function () use ($request, $payload, $fromWallet, $toWallet) {
            $transaction = Transaction::create([
                'user_id' => $request->user()->id,
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id' => $toWallet->id,
                'type' => 'transfer',
                'amount' => $payload['amount'],
                'reference' => Transaction::generateReference(),
                'description' => $payload['description'] ?? 'Admin wallet transfer',
                'status' => 'completed',
            ]);

            $fromWallet->balance = (float) $fromWallet->balance - (float) $payload['amount'];
            $toWallet->balance = (float) $toWallet->balance + (float) $payload['amount'];

            $fromWallet->save();
            $toWallet->save();

            return response()->json([
                'message' => 'Wallet transfer completed successfully',
                'transaction' => $transaction->load(['fromWallet', 'toWallet']),
            ]);
        });
    }

    public function freezeWallet(Request $request, $id)
    {
        $request->validate([
            'is_frozen' => 'required|boolean',
            'reason' => 'nullable|string',
        ]);

        $wallet = Wallet::findOrFail($id);
        $wallet->update([
            'is_frozen' => $request->is_frozen,
            'freeze_reason' => $request->is_frozen ? $request->reason : null,
        ]);

        return response()->json([
            'message' => $request->is_frozen ? 'Wallet frozen successfully' : 'Wallet unfrozen successfully',
            'wallet' => $wallet
        ]);
    }

    public function toggleUser(Request $request, $id)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $user = User::findOrFail($id);
        
        $user->update(['is_active' => $request->is_active]);

        return response()->json([
            'message' => $request->is_active ? 'User activated successfully' : 'User deactivated successfully',
            'user' => $user
        ]);
    }

    public function dashboard()
    {
        $totalCommission = CommissionTransaction::sum('commission_amount');

        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'total_master_distributors' => User::where('role', 'master_distributor')->count(),
            'total_super_distributors' => User::where('role', 'super_distributor')->count(),
            'total_distributors' => User::where('role', 'distributor')->count(),
            'total_retailers' => User::where('role', 'retailer')->count(),
            'total_wallets' => Wallet::count(),
            'frozen_wallets' => Wallet::where('is_frozen', true)->count(),
            'total_balance' => Wallet::sum('balance'),
            'total_commission' => (float) $totalCommission,
            'total_transactions' => Transaction::count(),
            'completed_transactions' => Transaction::where('status', 'completed')->count(),
            'pending_transactions' => Transaction::where('status', 'pending')->count(),
            'failed_transactions' => Transaction::where('status', 'failed')->count(),
        ];

        $recentTransactions = Transaction::with(['user', 'fromWallet', 'toWallet'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $topUsers = User::withCount(['transactions'])
            ->withSum('wallets', 'balance')
            ->orderBy('wallets_sum_balance', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_transactions' => $recentTransactions,
            'top_users' => $topUsers
        ]);
    }

    public function userTransactions(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $transactions = $user->transactions()
            ->with(['fromWallet', 'toWallet'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transactions);
    }

    public function userWallets(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $wallets = $user->wallets()->get();

        return response()->json($wallets);
    }
}
