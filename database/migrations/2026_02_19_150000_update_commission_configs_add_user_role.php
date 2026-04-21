<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite cannot reliably drop indexed enum columns in-place.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('commission_configs', function (Blueprint $table) {
            // Drop the existing enum constraint and recreate with 'user' included
            $table->dropColumn('user_role');
        });
        
        Schema::table('commission_configs', function (Blueprint $table) {
            $table->enum('user_role', ['admin', 'distributor', 'retailer', 'user'])->comment('User role this commission applies to')->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('commission_configs', function (Blueprint $table) {
            $table->dropColumn('user_role');
        });
        
        Schema::table('commission_configs', function (Blueprint $table) {
            $table->enum('user_role', ['admin', 'distributor', 'retailer'])->comment('User role this commission applies to')->after('name');
        });
    }
};
