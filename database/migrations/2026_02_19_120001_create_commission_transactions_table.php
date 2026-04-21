<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commission_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_transaction_id')->constrained('transactions')->onDelete('cascade')->comment('Original transaction that generated commission');
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('User who received the commission');
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade')->comment('Wallet that received the commission');
            $table->enum('commission_type', ['admin', 'distributor'])->comment('Type of commission');
            $table->decimal('original_amount', 15, 2)->comment('Original transaction amount');
            $table->decimal('commission_percentage', 5, 2)->comment('Commission percentage applied');
            $table->decimal('commission_amount', 15, 2)->comment('Commission amount earned');
            $table->string('reference')->unique()->comment('Unique reference for commission transaction');
            $table->text('description')->nullable()->comment('Description of the commission transaction');
            $table->timestamps();
            
            $table->index(['original_transaction_id', 'commission_type'], 'comm_txn_orig_type_idx');
            $table->index(['user_id', 'commission_type'], 'comm_txn_user_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_transactions');
    }
};
