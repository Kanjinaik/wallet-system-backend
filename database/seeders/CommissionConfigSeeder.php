<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CommissionConfig;

class CommissionConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create commission configuration for retailers
        CommissionConfig::create([
            'name' => 'Retailer Withdrawal Commission',
            'user_role' => 'retailer',
            'admin_commission' => 5.00,
            'distributor_commission' => 3.00,
            'is_active' => true,
        ]);

        // Create commission configuration for distributors
        CommissionConfig::create([
            'name' => 'Distributor Withdrawal Commission',
            'user_role' => 'distributor',
            'admin_commission' => 3.00,
            'distributor_commission' => 0.00,
            'is_active' => true,
        ]);

        // Create commission configuration for super distributors
        CommissionConfig::create([
            'name' => 'Super Distributor Withdrawal Commission',
            'user_role' => 'super_distributor',
            'admin_commission' => 2.00,
            'distributor_commission' => 0.00,
            'is_active' => true,
        ]);

        // Create commission configuration for master distributors
        CommissionConfig::create([
            'name' => 'Master Distributor Withdrawal Commission',
            'user_role' => 'master_distributor',
            'admin_commission' => 1.00,
            'distributor_commission' => 0.00,
            'is_active' => true,
        ]);

        // Create commission configuration for admins
        CommissionConfig::create([
            'name' => 'Admin Withdrawal Commission',
            'user_role' => 'admin',
            'admin_commission' => 0.00,
            'distributor_commission' => 0.00,
            'is_active' => true,
        ]);
    }
}
