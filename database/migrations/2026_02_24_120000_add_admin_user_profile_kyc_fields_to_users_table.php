<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'alternate_mobile')) {
                $table->string('alternate_mobile', 20)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'business_name')) {
                $table->string('business_name')->nullable()->after('alternate_mobile');
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->text('address')->nullable()->after('business_name');
            }
            if (!Schema::hasColumn('users', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (!Schema::hasColumn('users', 'state')) {
                $table->string('state')->nullable()->after('city');
            }
            if (!Schema::hasColumn('users', 'profile_photo_path')) {
                $table->string('profile_photo_path')->nullable()->after('state');
            }
            if (!Schema::hasColumn('users', 'kyc_id_number')) {
                $table->string('kyc_id_number', 64)->nullable()->after('profile_photo_path');
            }
            if (!Schema::hasColumn('users', 'kyc_photo_path')) {
                $table->string('kyc_photo_path')->nullable()->after('kyc_id_number');
            }
            if (!Schema::hasColumn('users', 'address_proof_front_path')) {
                $table->string('address_proof_front_path')->nullable()->after('kyc_photo_path');
            }
            if (!Schema::hasColumn('users', 'address_proof_back_path')) {
                $table->string('address_proof_back_path')->nullable()->after('address_proof_front_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'last_name',
                'alternate_mobile',
                'business_name',
                'address',
                'city',
                'state',
                'profile_photo_path',
                'kyc_id_number',
                'kyc_photo_path',
                'address_proof_front_path',
                'address_proof_back_path',
            ];

            $existingColumns = array_filter($columns, fn (string $column) => Schema::hasColumn('users', $column));
            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
