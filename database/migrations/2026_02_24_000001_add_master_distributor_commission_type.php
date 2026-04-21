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

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE commission_transactions MODIFY commission_type ENUM('admin','master_distributor','distributor') NOT NULL COMMENT 'Type of commission'");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('commission_transactions')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE commission_transactions SET commission_type = 'admin' WHERE commission_type = 'master_distributor'");
            DB::statement("ALTER TABLE commission_transactions MODIFY commission_type ENUM('admin','distributor') NOT NULL COMMENT 'Type of commission'");
        }
    }
};
