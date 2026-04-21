<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLimit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@wallet.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'plain_password' => 'password',
                'phone' => '9876543210',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        if (!$admin->wallets()->exists()) {
            $admin->wallets()->create([
                'name' => 'Admin Main Wallet',
                'type' => 'main',
                'balance' => 100000,
            ]);
        }

        if (!$admin->walletLimits()->exists()) {
            $admin->walletLimits()->createMany([
                [
                    'limit_type' => 'daily',
                    'max_amount' => 1000000,
                    'reset_date' => now()->toDateString(),
                ],
                [
                    'limit_type' => 'monthly',
                    'max_amount' => 10000000,
                    'reset_date' => now()->startOfMonth()->toDateString(),
                ],
                [
                    'limit_type' => 'per_transaction',
                    'max_amount' => 500000,
                ],
            ]);
        }

        // Create a regular test user
        $user = User::firstOrCreate(
            ['email' => 'user@wallet.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'plain_password' => 'password',
                'phone' => '9876543211',
                'role' => 'user',
                'is_active' => true,
            ]
        );

        if (!$user->wallets()->exists()) {
            $user->wallets()->create([
                'name' => 'Main Wallet',
                'type' => 'main',
                'balance' => 5000,
            ]);

            $user->wallets()->create([
                'name' => 'Savings Wallet',
                'type' => 'sub',
                'balance' => 2000,
            ]);
        }

        if (!$user->walletLimits()->exists()) {
            $user->walletLimits()->createMany([
                [
                    'limit_type' => 'daily',
                    'max_amount' => 500000,
                    'reset_date' => now()->toDateString(),
                ],
                [
                    'limit_type' => 'monthly',
                    'max_amount' => 500000,
                    'reset_date' => now()->startOfMonth()->toDateString(),
                ],
                [
                    'limit_type' => 'per_transaction',
                    'max_amount' => 500000,
                ],
            ]);
        }

        $this->command->info('Admin and Test users created successfully!');
        $this->command->info('Admin: admin@wallet.com / password');
        $this->command->info('Test User: user@wallet.com / password');
    }
}
