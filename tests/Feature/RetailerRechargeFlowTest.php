<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RetailerRechargeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_status_failure_refunds_pending_recharge_wallet(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        $wallet = $retailer->wallets()->create([
            'name' => 'Retailer Main',
            'type' => 'main',
            'balance' => 300,
        ]);

        $transaction = Transaction::create([
            'user_id' => $retailer->id,
            'from_wallet_id' => $wallet->id,
            'type' => 'recharge',
            'amount' => 100,
            'reference' => Transaction::generateReference(),
            'description' => 'Recharge',
            'status' => 'pending',
            'metadata' => [
                'wallet_debited' => true,
                'wallet_refunded' => false,
                'provider_request_id' => 'REQ12345678901234567890123456789012',
                'provider_txn_ref_id' => 'TXNREF123456',
            ],
        ]);

        $response = $this->postJson('/api/recharge/push-status', [
            'requestId' => 'REQ12345678901234567890123456789012',
            'responseCode' => '300',
            'responseReason' => 'Refund',
            'txnRefId' => 'TXNREF123456',
            'approvalRefNumber' => 'APR123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('transaction_id', $transaction->id)
            ->assertJsonPath('status', 'failed');

        $this->assertSame('400.00', $wallet->fresh()->balance);
        $this->assertTrue((bool) ($transaction->fresh()->metadata['wallet_refunded'] ?? false));
        $this->assertSame('failed', $transaction->fresh()->status);
    }

    public function test_pending_recharge_sync_command_marks_old_pending_transaction_completed(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        $wallet = $retailer->wallets()->create([
            'name' => 'Retailer Main',
            'type' => 'main',
            'balance' => 500,
        ]);

        $transaction = Transaction::create([
            'user_id' => $retailer->id,
            'from_wallet_id' => $wallet->id,
            'type' => 'recharge',
            'amount' => 100,
            'reference' => Transaction::generateReference(),
            'description' => 'Recharge',
            'status' => 'pending',
            'metadata' => [
                'wallet_debited' => true,
                'wallet_refunded' => false,
                'provider_request_id' => 'REQSYNC1234567890123456789012345678',
            ],
        ]);
        $transaction->forceFill([
            'created_at' => Carbon::now()->subMinutes(20),
            'updated_at' => Carbon::now()->subMinutes(20),
        ])->saveQuietly();

        $this->artisan('recharge:sync-pending', ['--minutes' => 15, '--limit' => 10])
            ->assertExitCode(0);

        $transaction->refresh();

        $this->assertSame('completed', $transaction->status);
        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertSame('completed', $transaction->metadata['provider_final_status'] ?? null);
    }
}
