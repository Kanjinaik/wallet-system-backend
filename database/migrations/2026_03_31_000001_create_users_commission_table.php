<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users_commission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('user_name');
            $table->string('agent_id');
            $table->decimal('total_commission', 15, 2)->default(0);
            $table->decimal('withdrawal_commission', 15, 2)->default(0);
            $table->decimal('available_commission', 15, 2)->default(0);
            $table->timestamps();

            $table->unique('user_id');
            $table->index('agent_id');
        });

        $timestamp = now();
        $rolePrefixes = [
            'master_distributor' => 'MD',
            'super_distributor' => 'SD',
            'distributor' => 'DT',
            'admin' => 'AD',
        ];

        $rows = DB::table('users')
            ->select('id', 'name', 'role')
            ->whereIn('role', array_keys($rolePrefixes))
            ->get()
            ->map(function ($user) use ($rolePrefixes, $timestamp) {
                return [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'agent_id' => 'XT' . ($rolePrefixes[$user->role] ?? 'US') . str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
                    'total_commission' => 0,
                    'withdrawal_commission' => 0,
                    'available_commission' => 0,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            })
            ->all();

        if (!empty($rows)) {
            DB::table('users_commission')->insert($rows);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_commission');
    }
};
