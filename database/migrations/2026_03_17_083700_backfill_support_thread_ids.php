<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('support_messages') &&
            Schema::hasTable('support_threads') &&
            Schema::hasColumn('support_messages', 'support_thread_id') &&
            Schema::hasColumn('support_messages', 'support_ticket_id')
        ) {
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
        if (Schema::hasTable('support_messages') && Schema::hasColumn('support_messages', 'support_thread_id')) {
            DB::table('support_messages')->update(['support_thread_id' => null]);
        }
    }
};
