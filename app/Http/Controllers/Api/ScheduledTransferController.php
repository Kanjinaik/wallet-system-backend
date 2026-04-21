<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduledTransfer;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduledTransferController extends Controller
{
    public function index(Request $request)
    {
        $transfers = $request->user()->scheduledTransfers()
            ->with(['fromWallet', 'toWallet'])
            ->orderBy('next_execution_at', 'asc')
            ->get();
        
        return response()->json($transfers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'from_wallet_id' => 'required|exists:wallets,id',
            'to_wallet_id' => 'required|exists:wallets,id|different:from_wallet_id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'frequency' => 'required|in:daily,weekly,monthly,yearly,once',
            'scheduled_at' => 'required|date|after:now',
        ]);

        $user = $request->user();
        
        // Verify wallet ownership
        $fromWallet = $user->wallets()->findOrFail($request->from_wallet_id);
        $toWallet = $user->wallets()->findOrFail($request->to_wallet_id);

        // Allow scheduled transfers from any wallet (frozen or unfrozen)
        // Only check balance and other validations

        // Calculate next execution time
        $scheduledAt = now()->parse($request->scheduled_at);
        $nextExecutionAt = $this->calculateNextExecution($scheduledAt, $request->frequency);

        $transfer = $user->scheduledTransfers()->create([
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'amount' => $request->amount,
            'description' => $request->description,
            'frequency' => $request->frequency,
            'scheduled_at' => $scheduledAt,
            'next_execution_at' => $nextExecutionAt,
            'is_active' => true,
        ]);

        return response()->json($transfer, 201);
    }

    public function show(Request $request, $id)
    {
        $transfer = $request->user()->scheduledTransfers()
            ->with(['fromWallet', 'toWallet'])
            ->findOrFail($id);
        
        return response()->json($transfer);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'amount' => 'sometimes|required|numeric|min:0.01',
            'description' => 'sometimes|nullable|string',
            'frequency' => 'sometimes|required|in:daily,weekly,monthly,yearly,once',
            'scheduled_at' => 'sometimes|required|date|after:now',
            'is_active' => 'sometimes|required|boolean',
        ]);

        $transfer = $request->user()->scheduledTransfers()->findOrFail($id);

        $transfer->update($request->all());

        // Recalculate next execution if frequency or scheduled_at changed
        if ($request->has('frequency') || $request->has('scheduled_at')) {
            $frequency = $request->frequency ?? $transfer->frequency;
            $scheduledAt = $request->scheduled_at ? now()->parse($request->scheduled_at) : $transfer->scheduled_at;
            $transfer->next_execution_at = $this->calculateNextExecution($scheduledAt, $frequency);
            $transfer->save();
        }

        return response()->json($transfer);
    }

    public function destroy(Request $request, $id)
    {
        $transfer = $request->user()->scheduledTransfers()->findOrFail($id);
        $transfer->delete();

        return response()->json(['message' => 'Scheduled transfer deleted successfully']);
    }

    public function toggle(Request $request, $id)
    {
        $transfer = $request->user()->scheduledTransfers()->findOrFail($id);
        $transfer->update(['is_active' => !$transfer->is_active]);

        return response()->json([
            'message' => $transfer->is_active ? 'Scheduled transfer activated' : 'Scheduled transfer deactivated',
            'transfer' => $transfer
        ]);
    }

    public function executeScheduledTransfers()
    {
        $transfers = ScheduledTransfer::where('is_active', true)
            ->where('next_execution_at', '<=', now())
            ->with(['fromWallet', 'toWallet', 'user'])
            ->get();

        $executed = 0;
        $failed = 0;

        foreach ($transfers as $transfer) {
            try {
                DB::transaction(function () use ($transfer, &$executed, &$failed) {
                    // Check if wallet has sufficient balance
                    if ($transfer->fromWallet->balance < $transfer->amount) {
                        $failed++;
                        return;
                    }

                    // Check if wallets are frozen
                    if ($transfer->fromWallet->is_frozen || $transfer->toWallet->is_frozen) {
                        $failed++;
                        return;
                    }

                    // Create and process transaction
                    $transaction = $transfer->user->transactions()->create([
                        'from_wallet_id' => $transfer->from_wallet_id,
                        'to_wallet_id' => $transfer->to_wallet_id,
                        'type' => 'transfer',
                        'amount' => $transfer->amount,
                        'reference' => Transaction::generateReference(),
                        'description' => $transfer->description ? "Scheduled: " . $transfer->description : "Scheduled transfer",
                        'status' => 'completed',
                        'metadata' => [
                            'scheduled_transfer_id' => $transfer->id,
                            'auto_executed' => true
                        ]
                    ]);

                    // Update wallet balances
                    $transfer->fromWallet->balance -= $transfer->amount;
                    $transfer->toWallet->balance += $transfer->amount;
                    
                    $transfer->fromWallet->save();
                    $transfer->toWallet->save();

                    // Update next execution time
                    if ($transfer->frequency === 'once') {
                        $transfer->is_active = false;
                    } else {
                        $transfer->next_execution_at = $transfer->calculateNextExecution();
                    }
                    
                    $transfer->save();
                    $executed++;
                });
            } catch (\Exception $e) {
                $failed++;
                \Log::error('Scheduled transfer execution failed', [
                    'transfer_id' => $transfer->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'message' => 'Scheduled transfers processed',
            'executed' => $executed,
            'failed' => $failed,
            'total' => $transfers->count()
        ]);
    }

    private function calculateNextExecution($scheduledAt, $frequency)
    {
        return match($frequency) {
            'daily' => $scheduledAt->copy()->addDay(),
            'weekly' => $scheduledAt->copy()->addWeek(),
            'monthly' => $scheduledAt->copy()->addMonth(),
            'yearly' => $scheduledAt->copy()->addYear(),
            'once' => $scheduledAt,
            default => $scheduledAt,
        };
    }
}
