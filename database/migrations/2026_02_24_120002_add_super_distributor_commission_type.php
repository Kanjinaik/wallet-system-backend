<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commission_transactions')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE commission_transactions MODIFY commission_type ENUM('admin','master_distributor','super_distributor','distributor') NOT NULL COMMENT 'Type of commission'");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('commission_transactions')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE commission_transactions SET commission_type = 'master_distributor' WHERE commission_type = 'super_distributor'");
            DB::statement("ALTER TABLE commission_transactions MODIFY commission_type ENUM('admin','master_distributor','distributor') NOT NULL COMMENT 'Type of commission'");
        }
    }
};
