<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawRequest;
use App\Services\ErtitechPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class RealTimeWithdrawalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_withdraw_marks_completed_when_gateway_confirms_immediately(): void
    {
        [$retailer, $wallet, $adminWallet] = $this->seedWithdrawalActors();

        $mock = Mockery::mock(ErtitechPayoutService::class);
        $mock->shouldReceive('createPayout')->once()->andReturn([
            'mode' => 'ertitech_payout',
            'status' => 'PAID',
            'utr' => 'UTR123456',
        ]);
        $mock->shouldReceive('getPayoutProcessingState')->once()->andReturn('completed');
        $this->app->instance(ErtitechPayoutService::class, $mock);

        Sanctum::actingAs($retailer);

        $response = $this->postJson('/api/withdraw', [
            'wallet_id' => $wallet->id,
            'amount' => 500,
            'bank_account' => '123456789012',
            'ifsc_code' => 'SBIN0001234',
            'account_holder_name' => 'Retailer Test',
            'beneficiary_mobile' => '9123456789',
        ]);

        $response->assertOk()
            ->assertJsonPath('processing_state', 'completed')
            ->assertJsonPath('transaction_status', 'completed')
            ->assertJsonPath('withdraw_request_status', 'processed');

        $transaction = Transaction::query()->where('type', 'withdraw')->firstOrFail();
        $withdrawRequest = WithdrawRequest::query()->firstOrFail();

        $this->assertSame('1495.00', $wallet->fresh()->balance);
        $this->assertSame('5.00', $adminWallet->fresh()->balance);
        $this->assertSame('completed', $transaction->status);
        $this->assertSame('9123456789', $transaction->metadata['beneficiary_mobile'] ?? null);
        $this->assertSame('processed', $withdrawRequest->status);
        $this->assertSame('completed', $withdrawRequest->metadata['processing_state'] ?? null);
    }

    public function test_auto_withdraw_stays_pending_when_gateway_accepts_but_bank_confirmation_is_pending(): void
    {
        [$retailer, $wallet, $adminWallet] = $this->seedWithdrawalActors();

        $mock = Mockery::mock(ErtitechPayoutService::class);
        $mock->shouldReceive('createPayout')->once()->andReturn([
            'mode' => 'ertitech_payout',
            'status' => 'PENDING',
        ]);
        $mock->shouldReceive('getPayoutProcessingState')->once()->andReturn('pending');
        $this->app->instance(ErtitechPayoutService::class, $mock);

        Sanctum::actingAs($retailer);

        $response = $this->postJson('/api/withdraw', [
            'wallet_id' => $wallet->id,
            'amount' => 500,
            'bank_account' => '123456789012',
            'ifsc_code' => 'SBIN0001234',
            'account_holder_name' => 'Retailer Test',
            'beneficiary_mobile' => '9123456789',
        ]);

        $response->assertOk()
            ->assertJsonPath('processing_state', 'pending')
            ->assertJsonPath('transaction_status', 'pending')
            ->assertJsonPath('withdraw_request_status', 'pending');

        $transaction = Transaction::query()->where('type', 'withdraw')->firstOrFail();
        $withdrawRequest = WithdrawRequest::query()->firstOrFail();

        $this->assertSame('1495.00', $wallet->fresh()->balance);
        $this->assertSame('5.00', $adminWallet->fresh()->balance);
        $this->assertSame('pending', $transaction->status);
        $this->assertSame('pending', $withdrawRequest->status);
        $this->assertSame('pending', $withdrawRequest->metadata['processing_state'] ?? null);
    }

    private function seedWithdrawalActors(): array
    {
        AdminSetting::setValue('withdraw_approval_mode', 'auto');
        AdminSetting::setValue('withdraw_min_amount', '100');
        AdminSetting::setValue('withdraw_max_per_tx', '500000');

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'phone' => '9999999999',
        ]);
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'is_active' => true,
            'phone' => '9876543210',
            'kyc_status' => 'approved',
        ]);

        $adminWallet = $admin->wallets()->create([
            'name' => 'Admin Main',
            'type' => 'main',
            'balance' => 0,
        ]);

        $wallet = $retailer->wallets()->create([
            'name' => 'Retailer Main',
            'type' => 'main',
            'balance' => 2000,
        ]);

        return [$retailer, $wallet, $adminWallet];
    }
}
