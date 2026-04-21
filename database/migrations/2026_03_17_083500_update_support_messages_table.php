<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('support_messages', 'support_thread_id')) {
                $table->unsignedBigInteger('support_thread_id')->nullable()->after('id');
                $table->index('support_thread_id');
                $table->foreign('support_thread_id')
                    ->references('id')
                    ->on('support_threads')
                    ->onDelete('cascade');
            }

            if (!Schema::hasColumn('support_messages', 'sender_id')) {
                $table->unsignedBigInteger('sender_id')->nullable()->after('sender_type');
                $table->index(['sender_type', 'sender_id']);
            }

            if (!Schema::hasColumn('support_messages', 'file_url')) {
                $table->string('file_url')->nullable()->after('message');
            }
        });

        // Preserve historical data by copying legacy user_id into sender_id when available.
        if (Schema::hasColumn('support_messages', 'sender_id') && Schema::hasColumn('support_messages', 'user_id')) {
            DB::table('support_messages')
                ->whereNull('sender_id')
                ->update(['sender_id' => DB::raw('user_id')]);
        }

        // Backfill support_thread_id from legacy support_ticket_id when they match existing threads.
        if (Schema::hasTable('support_threads') && Schema::hasColumn('support_messages', 'support_thread_id') && Schema::hasColumn('support_messages', 'support_ticket_id')) {
            DB::statement("
                UPDATE support_messages sm
                JOIN support_threads st ON st.id = sm.support_ticket_id
                SET sm.support_thread_id = sm.support_ticket_id
                WHERE sm.support_thread_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            if (Schema::hasColumn('support_messages', 'support_thread_id')) {
                $table->dropForeign(['support_thread_id']);
                $table->dropIndex(['support_thread_id']);
                $table->dropColumn('support_thread_id');
            }

            if (Schema::hasColumn('support_messages', 'sender_id')) {
                $table->dropIndex(['sender_type', 'sender_id']);
                $table->dropColumn('sender_id');
            }

            if (Schema::hasColumn('support_messages', 'file_url')) {
                $table->dropColumn('file_url');
            }
        });
    }
};
