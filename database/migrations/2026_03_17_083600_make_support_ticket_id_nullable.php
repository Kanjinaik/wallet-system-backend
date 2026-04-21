<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('support_messages', 'support_ticket_id')) {
            DB::statement('ALTER TABLE support_messages MODIFY support_ticket_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_messages', 'support_ticket_id')) {
            DB::statement('ALTER TABLE support_messages MODIFY support_ticket_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
