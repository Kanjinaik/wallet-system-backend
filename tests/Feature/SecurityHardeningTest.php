<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_does_not_store_plain_password(): void
    {
        Storage::fake('public');

        $response = $this->postJson('/api/register', [
            'name' => 'Security User',
            'email' => 'security@example.com',
            'password' => 'secret123',
            'phone' => '9999999999',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'security@example.com',
            'plain_password' => null,
        ]);
    }

    public function test_forgot_password_hides_reset_token_outside_development(): void
    {
        Mail::fake();
        config()->set('app.env', 'production');
        config()->set('app.debug', false);

        User::factory()->create([
            'email' => 'reset@example.com',
            'role' => 'user',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response->assertOk()
            ->assertJsonMissingPath('reset_token');
    }

    public function test_withdraw_otp_is_not_exposed_outside_development(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', false);

        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        $wallet = $user->wallets()->create([
            'name' => 'Main Wallet',
            'type' => 'main',
            'balance' => 500,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/withdraw/request-otp', [
            'wallet_id' => $wallet->id,
            'amount' => 100,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'otp' => null,
            ]);
    }

    public function test_static_wallet_limits_route_is_resolved_before_dynamic_wallet_id_route(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/wallets/limits')
            ->assertOk();
    }

    public function test_transactions_export_route_is_resolved_before_dynamic_transaction_id_route(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->get('/api/transactions/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_register_is_blocked_after_admin_exists(): void
    {
        User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'email' => 'admin@example.com',
        ]);

        $this->get('/admin/register')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_users_api_does_not_expose_plain_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        User::factory()->create([
            'role' => 'retailer',
            'is_active' => true,
            'plain_password' => 'secret123',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users');

        $response->assertOk();
        $this->assertStringNotContainsString('plain_password', $response->getContent());
        $this->assertStringNotContainsString('secret123', $response->getContent());
    }
}
