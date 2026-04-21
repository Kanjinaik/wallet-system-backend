<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function rebuildSqliteTransactionsTable(array $types): void
    {
        $quotedTypes = "'" . implode("', '", $types) . "'";

        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('DROP TABLE IF EXISTS transactions_new');
        DB::statement("
            CREATE TABLE transactions_new (
                id integer primary key autoincrement not null,
                user_id integer not null,
                from_wallet_id integer,
                to_wallet_id integer,
                type varchar check (type in ($quotedTypes)) not null,
                amount numeric not null,
                reference varchar not null unique,
                description text,
                status varchar check (status in ('pending', 'completed', 'failed', 'cancelled')) not null default 'pending',
                metadata text,
                created_at datetime,
                updated_at datetime,
                foreign key(user_id) references users(id) on delete cascade,
                foreign key(from_wallet_id) references wallets(id) on delete set null,
                foreign key(to_wallet_id) references wallets(id) on delete set null
            )
        ");
        DB::statement('INSERT INTO transactions_new (id, user_id, from_wallet_id, to_wallet_id, type, amount, reference, description, status, metadata, created_at, updated_at) SELECT id, user_id, from_wallet_id, to_wallet_id, type, amount, reference, description, status, metadata, created_at, updated_at FROM transactions');
        DB::statement('DROP TABLE transactions');
        DB::statement('ALTER TABLE transactions_new RENAME TO transactions');
        DB::statement('PRAGMA foreign_keys=ON');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTransactionsTable(['deposit', 'withdraw', 'transfer', 'receive', 'deduction']);
            return;
        }

        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('deposit', 'withdraw', 'transfer', 'receive', 'deduction')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTransactionsTable(['deposit', 'withdraw', 'transfer', 'receive']);
            return;
        }

        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('deposit', 'withdraw', 'transfer', 'receive')");
    }
};
