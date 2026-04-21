<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLimit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or update Admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // Create or update Master Distributor user
        $master = User::updateOrCreate(
            ['email' => 'master@example.com'],
            [
                'name' => 'Master Distributor User',
                'password' => Hash::make('password'),
                'role' => 'master_distributor',
                'is_active' => true,
            ]
        );

        if (!$master->wallets()->where('type', 'sub')->exists()) {
            Wallet::create([
                'user_id' => $master->id,
                'name' => 'Master Distributor Wallet',
                'type' => 'sub',
                'balance' => 30000.00,
                'is_frozen' => false,
            ]);
        }

        // Create or update Super Distributor user
        $super = User::updateOrCreate(
            ['email' => 'super@example.com'],
            [
                'name' => 'Super Distributor User',
                'password' => Hash::make('password'),
                'role' => 'super_distributor',
                'distributor_id' => $master->id,
                'is_active' => true,
            ]
        );

        if (!$super->wallets()->where('type', 'sub')->exists()) {
            Wallet::create([
                'user_id' => $super->id,
                'name' => 'Super Distributor Wallet',
                'type' => 'sub',
                'balance' => 25000.00,
                'is_frozen' => false,
            ]);
        }

        // Create main wallet for admin if not exists
        if (!$admin->wallets()->where('type', 'main')->exists()) {
            Wallet::create([
                'user_id' => $admin->id,
                'name' => 'Admin Main Wallet',
                'type' => 'main',
                'balance' => 50000.00,
                'is_frozen' => false,
            ]);
        }

        // Create or update Distributor user
        $distributor = User::updateOrCreate(
            ['email' => 'distributor@example.com'],
            [
                'name' => 'Distributor User',
                'password' => Hash::make('password'),
                'role' => 'distributor',
                'distributor_id' => $super->id,
                'is_active' => true,
            ]
        );

        // Create sub wallet for distributor if not exists
        if (!$distributor->wallets()->where('type', 'sub')->exists()) {
            Wallet::create([
                'user_id' => $distributor->id,
                'name' => 'Distributor Sub Wallet',
                'type' => 'sub',
                'balance' => 20000.00,
                'is_frozen' => false,
            ]);
        }

        // Create or update Retailer user
        $retailer = User::updateOrCreate(
            ['email' => 'retailer@example.com'],
            [
                'name' => 'Retailer User',
                'password' => Hash::make('password'),
                'role' => 'retailer',
                'distributor_id' => $distributor->id,
                'is_active' => true,
            ]
        );

        // Create sub wallet for retailer if not exists
        if (!$retailer->wallets()->where('type', 'sub')->exists()) {
            Wallet::create([
                'user_id' => $retailer->id,
                'name' => 'Retailer Sub Wallet',
                'type' => 'sub',
                'balance' => 10000.00,
                'is_frozen' => false,
            ]);
        }

        // Ensure role hierarchy mapping remains intact
        if ((int) $retailer->distributor_id !== (int) $distributor->id) {
            $retailer->distributor_id = $distributor->id;
            $retailer->save();
        }

        if ((int) $super->distributor_id !== (int) $master->id) {
            $super->distributor_id = $master->id;
            $super->save();
        }

        if ((int) $distributor->distributor_id !== (int) $super->id) {
            $distributor->distributor_id = $super->id;
            $distributor->save();
        }

        // Ensure default limits exist for all hierarchy users
        $this->ensureLimits($admin->id, 1000000, 10000000, 500000);
        $this->ensureLimits($master->id, 500000, 5000000, 200000);
        $this->ensureLimits($super->id, 500000, 5000000, 200000);
        $this->ensureLimits($distributor->id, 500000, 5000000, 200000);
        $this->ensureLimits($retailer->id, 10000, 100000, 50000);

        $this->command->info('Test users created successfully!');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Master Distributor: master@example.com / password');
        $this->command->info('Super Distributor: super@example.com / password');
        $this->command->info('Distributor: distributor@example.com / password');
        $this->command->info('Retailer: retailer@example.com / password');
    }

    private function ensureLimits(int $userId, float $daily, float $monthly, float $perTransaction): void
    {
        WalletLimit::updateOrCreate(
            ['user_id' => $userId, 'limit_type' => 'daily'],
            [
                'max_amount' => $daily,
                'transaction_count' => 0,
                'total_amount' => 0,
                'reset_date' => now()->toDateString(),
            ]
        );

        WalletLimit::updateOrCreate(
            ['user_id' => $userId, 'limit_type' => 'monthly'],
            [
                'max_amount' => $monthly,
                'transaction_count' => 0,
                'total_amount' => 0,
                'reset_date' => now()->startOfMonth()->toDateString(),
            ]
        );

        WalletLimit::updateOrCreate(
            ['user_id' => $userId, 'limit_type' => 'per_transaction'],
            [
                'max_amount' => $perTransaction,
                'transaction_count' => 0,
                'total_amount' => 0,
                'reset_date' => null,
            ]
        );
    }
}
