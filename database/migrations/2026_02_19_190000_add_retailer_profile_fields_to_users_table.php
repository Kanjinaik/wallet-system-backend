<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('bank_account_name')->nullable()->after('is_active');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
            $table->string('bank_ifsc_code', 32)->nullable()->after('bank_account_number');
            $table->string('bank_name')->nullable()->after('bank_ifsc_code');
            $table->string('kyc_document_path')->nullable()->after('bank_name');
            $table->string('kyc_status', 32)->default('pending')->after('kyc_document_path');
            $table->string('withdraw_otp_code', 10)->nullable()->after('kyc_status');
            $table->timestamp('withdraw_otp_expires_at')->nullable()->after('withdraw_otp_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'bank_account_name',
                'bank_account_number',
                'bank_ifsc_code',
                'bank_name',
                'kyc_document_path',
                'kyc_status',
                'withdraw_otp_code',
                'withdraw_otp_expires_at',
            ]);
        });
    }
};
