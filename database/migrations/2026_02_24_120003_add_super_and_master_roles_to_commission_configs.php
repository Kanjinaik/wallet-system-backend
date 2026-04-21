<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commission_configs')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE commission_configs MODIFY user_role ENUM('admin','master_distributor','super_distributor','distributor','retailer','user') NOT NULL COMMENT 'User role this commission applies to'");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('commission_configs')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE commission_configs SET user_role = 'distributor' WHERE user_role IN ('master_distributor','super_distributor')");
            DB::statement("ALTER TABLE commission_configs MODIFY user_role ENUM('admin','distributor','retailer','user') NOT NULL COMMENT 'User role this commission applies to'");
        }
    }
};
