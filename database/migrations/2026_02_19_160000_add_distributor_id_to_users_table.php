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
        if (Schema::hasColumn('users', 'distributor_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // SQLite add-column path: add nullable FK id column safely.
            if (DB::getDriverName() === 'sqlite') {
                $table->unsignedBigInteger('distributor_id')->nullable()->after('role');
                return;
            }

            $table->foreignId('distributor_id')
                ->nullable()
                ->after('role')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('users', 'distributor_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropConstrainedForeignId('distributor_id');
                return;
            }

            $table->dropColumn('distributor_id');
        });
    }
};
