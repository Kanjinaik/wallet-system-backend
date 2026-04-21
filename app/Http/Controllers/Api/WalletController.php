<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $wallets = $request->user()->wallets()->get();
        return response()->json($wallets);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:sub',
        ]);

        $wallet = $request->user()->wallets()->create([
            'name' => $request->name,
            'type' => $request->type,
            'balance' => 0,
        ]);

        return response()->json($wallet, 201);
    }

    public function show(Request $request, $id)
    {
        $wallet = $request->user()->wallets()->findOrFail($id);
        return response()->json($wallet);
    }

    public function freeze(Request $request, $id)
    {
        $request->validate([
            'is_frozen' => 'required|boolean',
            'reason' => 'nullable|string',
        ]);

        $wallet = $request->user()->wallets()->findOrFail($id);
        $wallet->update([
            'is_frozen' => $request->is_frozen,
            'freeze_reason' => $request->is_frozen ? $request->reason : null,
        ]);

        return response()->json([
            'message' => $request->is_frozen ? 'Wallet frozen successfully' : 'Wallet unfrozen successfully',
            'wallet' => $wallet
        ]);
    }

    public function balance(Request $request, $id)
    {
        $wallet = $request->user()->wallets()->findOrFail($id);
        return response()->json([
            'wallet_id' => $wallet->id,
            'balance' => $wallet->balance,
            'is_frozen' => $wallet->is_frozen
        ]);
    }

    public function limits(Request $request)
    {
        $limits = $request->user()->walletLimits()->get();
        return response()->json($limits);
    }

    public function updateLimits(Request $request)
    {
        $request->validate([
            'limits' => 'required|array',
            'limits.*.limit_type' => 'required|in:daily,monthly,per_transaction',
            'limits.*.max_amount' => 'required|numeric|min:0',
        ]);

        foreach ($request->limits as $limitData) {
            $request->user()->walletLimits()->updateOrCreate(
                ['limit_type' => $limitData['limit_type']],
                ['max_amount' => $limitData['max_amount']]
            );
        }

        return response()->json(['message' => 'Limits updated successfully']);
    }

    public function checkLimits(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:daily,monthly,per_transaction',
        ]);

        $canProceed = WalletLimit::checkLimit(
            $request->user()->id,
            $request->amount,
            $request->type
        );

        return response()->json(['can_proceed' => $canProceed]);
    }
}
