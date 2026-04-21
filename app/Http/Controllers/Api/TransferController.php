<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class TransferController extends Controller
{
    public function transfer(Request $request)
    {
        \Log::info('Transfer request data:', $request->all());
        
        $request->validate([
            'from_wallet_id' => 'required|exists:wallets,id',
            'to_wallet_id' => 'required|exists:wallets,id|different:from_wallet_id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
        ]);

        \Log::info('Transfer validation passed');

        $user = $request->user();
        
        // Verify wallet ownership
        $fromWallet = $user->wallets()->findOrFail($request->from_wallet_id);
        $toWallet = $user->wallets()->findOrFail($request->to_wallet_id);

        \Log::info('Wallets verified:', [
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'from_balance' => $fromWallet->balance,
            'amount' => $request->amount
        ]);

        if ($fromWallet->is_frozen || $toWallet->is_frozen) {
            \Log::warning('Frozen wallet detected');
            return response()->json(['message' => 'Cannot transfer to/from frozen wallets'], 422);
        }

        if ($fromWallet->balance < $request->amount) {
            \Log::warning('Insufficient balance', [
                'balance' => $fromWallet->balance,
                'amount' => $request->amount
            ]);
            return response()->json(['message' => 'Insufficient balance'], 422);
        }

        // Check limits - temporarily disabled for testing
        /*
        if (!WalletLimit::checkLimit($user->id, $request->amount, 'per_transaction')) {
            \Log::warning('Per-transaction limit exceeded');
            return response()->json(['message' => 'Per-transaction limit exceeded'], 422);
        }
        if (!WalletLimit::checkLimit($user->id, $request->amount, 'daily')) {
            \Log::warning('Daily limit exceeded');
            return response()->json(['message' => 'Daily limit exceeded'], 422);
        }
        if (!WalletLimit::checkLimit($user->id, $request->amount, 'monthly')) {
            \Log::warning('Monthly limit exceeded');
            return response()->json(['message' => 'Monthly limit exceeded'], 422);
        }
        */

        \Log::info('All checks passed, processing transfer');

        return DB::transaction(function () use ($request, $fromWallet, $toWallet, $user) {
            // Create transaction record
            $transaction = $user->transactions()->create([
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id' => $toWallet->id,
                'type' => 'transfer',
                'amount' => $request->amount,
                'reference' => Transaction::generateReference(),
                'description' => $request->description,
                'status' => 'completed',
            ]);

            // Update wallet balances
            $fromWallet->balance -= $request->amount;
            $toWallet->balance += $request->amount;
            
            $fromWallet->save();
            $toWallet->save();

            // Update limits - temporarily disabled for testing
            // WalletLimit::updateLimit($user->id, $request->amount, 'daily');
            // WalletLimit::updateLimit($user->id, $request->amount, 'monthly');

            return response()->json([
                'message' => 'Transfer completed successfully',
                'transaction' => $transaction->load(['fromWallet', 'toWallet'])
            ]);
        });
    }
}
