<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','admin','master_distributor','super_distributor','distributor','retailer') NOT NULL DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE users SET role = 'distributor' WHERE role = 'super_distributor'");
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','admin','master_distributor','distributor','retailer') NOT NULL DEFAULT 'user'");
        }
    }
};
