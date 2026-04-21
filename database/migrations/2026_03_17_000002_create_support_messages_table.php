<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_messages')) {
            return;
        }

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('support_thread_id');
            $table->string('sender_type'); // retailer | admin
            $table->unsignedBigInteger('sender_id');
            $table->text('message')->nullable();
            $table->string('file_url')->nullable();
            $table->timestamps();

            $table->index('support_thread_id');
            $table->index(['sender_type', 'sender_id']);
            $table->foreign('support_thread_id')->references('id')->on('support_threads')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
