<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\CommissionTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RetailerDepositCommissionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_retailer_deposit_deducts_fee_and_commissions_from_deposit_amount(): void
    {
        AdminSetting::setValue('sys_transaction_fee', '1.20');
        AdminSetting::setValue('sys_deposit_commission_admin', '0.02');
        AdminSetting::setValue('sys_deposit_commission_master_distributor', '0');
        AdminSetting::setValue('sys_deposit_commission_super_distributor', '0');
        AdminSetting::setValue('sys_deposit_commission_distributor', '0.03');

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $distributor = User::factory()->create([
            'role' => 'distributor',
            'is_active' => true,
        ]);
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'is_active' => true,
            'distributor_id' => $distributor->id,
        ]);

        $adminWallet = $admin->wallets()->create([
            'name' => 'Admin Main',
            'type' => 'main',
            'balance' => 0,
        ]);
        $distributorWallet = $distributor->wallets()->create([
            'name' => 'Distributor Sub',
            'type' => 'sub',
            'balance' => 0,
        ]);
        $retailerWallet = $retailer->wallets()->create([
            'name' => 'Retailer Main',
            'type' => 'main',
            'balance' => 0,
        ]);

        Sanctum::actingAs($retailer);

        $response = $this->postJson('/api/deposit', [
            'amount' => 1000,
            'wallet_id' => $retailerWallet->id,
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.net_credited', 998.3)
            ->assertJsonPath('data.commission.base_charge', 1.2)
            ->assertJsonPath('data.commission.admin_amount', 0.2)
            ->assertJsonPath('data.commission.master_distributor_amount', 0)
            ->assertJsonPath('data.commission.super_distributor_amount', 0)
            ->assertJsonPath('data.commission.distributor_amount', 0.3)
            ->assertJsonPath('data.commission.total_commission', 0.5)
            ->assertJsonPath('data.commission.total_deduction', 1.7);

        $this->assertSame('998.30', $retailerWallet->fresh()->balance);
        $this->assertSame('0.20', $adminWallet->fresh()->balance);
        $this->assertSame('0.30', $distributorWallet->fresh()->balance);

        $this->assertDatabaseCount('commission_transactions', 2);
        $this->assertSame(0.5, round((float) CommissionTransaction::sum('commission_amount'), 2));
    }
}
