<?php

namespace Tests\Unit;

use App\Models\AdminSetting;
use App\Models\User;
use App\Models\WithdrawRequest;
use App\Services\ErtitechPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErtitechPayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_uses_saved_admin_gateway_settings_and_beneficiary_mobile(): void
    {
        config()->set('services.ertitech_payout.base_url', '');
        config()->set('services.ertitech_payout.username', '');
        config()->set('services.ertitech_payout.password', '');
        config()->set('services.ertitech_payout.merchant_id', '');
        config()->set('services.ertitech_payout.wallet_id', '');
        config()->set('services.ertitech_payout.aes_key', '');
        config()->set('services.ertitech_payout.mode', 'test');

        AdminSetting::setValue('sys_gateway_ertitech_username', 'demo-user');
        AdminSetting::setValue('sys_gateway_ertitech_password', 'demo-pass');
        AdminSetting::setValue('sys_gateway_ertitech_merchant_id', 'MERCHANT123');
        AdminSetting::setValue('sys_gateway_ertitech_wallet_id', 'WALLET99');
        AdminSetting::setValue('sys_gateway_ertitech_aes_key', 'c7229cc66b89997bfcb3d223669075eb');
        AdminSetting::setValue('sys_gateway_ertitech_mode', 'test');

        $user = User::factory()->create([
            'role' => 'retailer',
            'phone' => '9876543210',
            'is_active' => true,
        ]);
        $wallet = $user->wallets()->create([
            'name' => 'Retailer Main',
            'type' => 'main',
            'balance' => 1000,
        ]);
        $withdrawRequest = WithdrawRequest::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 500,
            'net_amount' => 500,
            'status' => 'approved',
            'metadata' => [
                'bank_account' => '123456789012',
                'ifsc_code' => 'SBIN0001234',
                'account_holder_name' => 'Retailer Test',
                'beneficiary_mobile' => '9123456789',
            ],
        ]);

        $service = new ErtitechPayoutService();
        $result = $service->createPayout($withdrawRequest, $user, 500);

        $this->assertTrue($service->isConfigured());
        $this->assertSame('PAID', $result['status']);
        $this->assertSame('9123456789', data_get($result, 'decrypted_response.beneMobileNo'));
        $this->assertSame('WALLET99', $result['wallet_id']);
        $this->assertSame('completed', $service->getPayoutProcessingState($result));
    }

    public function test_service_reads_gateway_keys_saved_without_sys_prefix(): void
    {
        config()->set('services.ertitech_payout.base_url', '');
        config()->set('services.ertitech_payout.username', '');
        config()->set('services.ertitech_payout.password', '');
        config()->set('services.ertitech_payout.merchant_id', '');
        config()->set('services.ertitech_payout.wallet_id', '');
        config()->set('services.ertitech_payout.aes_key', '');
        config()->set('services.ertitech_payout.mode', 'test');

        AdminSetting::setValue('gateway_ertitech_username', 'demo-user');
        AdminSetting::setValue('gateway_ertitech_password', 'demo-pass');
        AdminSetting::setValue('gateway_ertitech_merchant_id', 'MERCHANT123');
        AdminSetting::setValue('gateway_ertitech_wallet_id', 'WALLET99');
        AdminSetting::setValue('gateway_ertitech_aes_key', 'c7229cc66b89997bfcb3d223669075eb');
        AdminSetting::setValue('gateway_ertitech_mode', 'test');

        $service = new ErtitechPayoutService();

        $this->assertTrue($service->isConfigured());
    }
}
