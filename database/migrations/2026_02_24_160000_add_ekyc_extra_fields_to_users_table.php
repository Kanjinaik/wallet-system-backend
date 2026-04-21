<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'kyc_document_type')) {
                $table->string('kyc_document_type', 32)->nullable()->after('kyc_id_number');
            }
            if (!Schema::hasColumn('users', 'kyc_selfie_path')) {
                $table->string('kyc_selfie_path')->nullable()->after('address_proof_back_path');
            }
            if (!Schema::hasColumn('users', 'kyc_liveness_verified')) {
                $table->boolean('kyc_liveness_verified')->default(false)->after('kyc_selfie_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $dropColumns = array_filter([
                Schema::hasColumn('users', 'kyc_document_type') ? 'kyc_document_type' : null,
                Schema::hasColumn('users', 'kyc_selfie_path') ? 'kyc_selfie_path' : null,
                Schema::hasColumn('users', 'kyc_liveness_verified') ? 'kyc_liveness_verified' : null,
            ]);

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
