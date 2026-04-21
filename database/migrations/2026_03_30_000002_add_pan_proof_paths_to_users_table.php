<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pan_proof_front_path')) {
                $table->string('pan_proof_front_path')->nullable()->after('pan_number');
            }
            if (!Schema::hasColumn('users', 'pan_proof_back_path')) {
                $table->string('pan_proof_back_path')->nullable()->after('pan_proof_front_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $dropColumns = array_filter([
                Schema::hasColumn('users', 'pan_proof_front_path') ? 'pan_proof_front_path' : null,
                Schema::hasColumn('users', 'pan_proof_back_path') ? 'pan_proof_back_path' : null,
            ]);

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
