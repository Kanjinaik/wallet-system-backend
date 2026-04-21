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
        Schema::create('commission_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Name of the commission configuration');
            $table->enum('user_role', ['admin', 'distributor', 'retailer'])->comment('User role this commission applies to');
            $table->decimal('admin_commission', 5, 2)->default(0)->comment('Commission percentage for admin');
            $table->decimal('distributor_commission', 5, 2)->default(0)->comment('Commission percentage for distributor');
            $table->boolean('is_active')->default(true)->comment('Whether this commission config is active');
            $table->timestamps();
            
            $table->index(['user_role', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_configs');
    }
};
