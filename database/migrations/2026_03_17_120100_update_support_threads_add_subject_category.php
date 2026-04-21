<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            if (!Schema::hasColumn('support_threads', 'subject')) {
                $table->string('subject')->after('user_id')->default('Support Ticket');
            }
            if (!Schema::hasColumn('support_threads', 'category')) {
                $table->string('category')->nullable()->after('subject');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            if (Schema::hasColumn('support_threads', 'subject')) {
                $table->dropColumn('subject');
            }
            if (Schema::hasColumn('support_threads', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
