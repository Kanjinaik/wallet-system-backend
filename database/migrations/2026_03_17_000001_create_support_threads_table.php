<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // retailer/user who created the ticket
            $table->unsignedBigInteger('admin_id')->nullable(); // admin who responded/owns
            $table->string('issue_type');
            $table->string('priority')->default('medium');
            $table->string('status')->default('open'); // open, in_progress, escalated, resolved
            $table->string('tx_id')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('admin_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_threads');
    }
};
