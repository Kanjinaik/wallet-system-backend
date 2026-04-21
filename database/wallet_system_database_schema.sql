-- Wallet System database schema reference
-- Generated from the current Laravel migrations in this project.
-- This file is a schema reference and does not change application flow.

-- table: admin_action_logs
CREATE TABLE "admin_action_logs" ("id" integer primary key autoincrement not null, "admin_user_id" integer not null, "action" varchar not null, "target_type" varchar, "target_id" integer, "metadata" text, "ip_address" varchar, "created_at" datetime, "updated_at" datetime, foreign key("admin_user_id") references "users"("id") on delete cascade);

-- table: admin_settings
CREATE TABLE "admin_settings" ("id" integer primary key autoincrement not null, "key" varchar not null, "value" text, "created_at" datetime, "updated_at" datetime);

-- table: cache
CREATE TABLE "cache" ("key" varchar not null, "value" text not null, "expiration" integer not null, primary key ("key"));

-- table: cache_locks
CREATE TABLE "cache_locks" ("key" varchar not null, "owner" varchar not null, "expiration" integer not null, primary key ("key"));

-- table: commission_configs
CREATE TABLE "commission_configs" ("id" integer primary key autoincrement not null, "name" varchar not null, "user_role" varchar check ("user_role" in ('admin', 'distributor', 'retailer', 'user')) not null, "admin_commission" numeric not null default '0', "distributor_commission" numeric not null default '0', "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime);

-- table: commission_overrides
CREATE TABLE "commission_overrides" ("id" integer primary key autoincrement not null, "user_id" integer not null, "admin_commission" numeric not null default '0', "distributor_commission" numeric not null default '0', "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade);

-- table: commission_transactions
CREATE TABLE "commission_transactions" ("id" integer primary key autoincrement not null, "original_transaction_id" integer not null, "user_id" integer not null, "wallet_id" integer not null, "commission_type" varchar check ("commission_type" in ('admin', 'distributor')) not null, "original_amount" numeric not null, "commission_percentage" numeric not null, "commission_amount" numeric not null, "reference" varchar not null, "description" text, "created_at" datetime, "updated_at" datetime, foreign key("original_transaction_id") references "transactions"("id") on delete cascade, foreign key("user_id") references "users"("id") on delete cascade, foreign key("wallet_id") references "wallets"("id") on delete cascade);

-- table: failed_jobs
CREATE TABLE "failed_jobs" ("id" integer primary key autoincrement not null, "uuid" varchar not null, "connection" text not null, "queue" text not null, "payload" text not null, "exception" text not null, "failed_at" datetime not null default CURRENT_TIMESTAMP);

-- table: job_batches
CREATE TABLE "job_batches" ("id" varchar not null, "name" varchar not null, "total_jobs" integer not null, "pending_jobs" integer not null, "failed_jobs" integer not null, "failed_job_ids" text not null, "options" text, "cancelled_at" integer, "created_at" integer not null, "finished_at" integer, primary key ("id"));

-- table: jobs
CREATE TABLE "jobs" ("id" integer primary key autoincrement not null, "queue" varchar not null, "payload" text not null, "attempts" integer not null, "reserved_at" integer, "available_at" integer not null, "created_at" integer not null);

-- table: migrations
CREATE TABLE "migrations" ("id" integer primary key autoincrement not null, "migration" varchar not null, "batch" integer not null);

-- table: password_reset_tokens
CREATE TABLE "password_reset_tokens" ("email" varchar not null, "token" varchar not null, "created_at" datetime, primary key ("email"));

-- table: personal_access_tokens
CREATE TABLE "personal_access_tokens" ("id" integer primary key autoincrement not null, "tokenable_type" varchar not null, "tokenable_id" integer not null, "name" text not null, "token" varchar not null, "abilities" text, "last_used_at" datetime, "expires_at" datetime, "created_at" datetime, "updated_at" datetime);

-- table: scheduled_transfers
CREATE TABLE "scheduled_transfers" ("id" integer primary key autoincrement not null, "user_id" integer not null, "from_wallet_id" integer not null, "to_wallet_id" integer not null, "amount" numeric not null, "description" text, "frequency" varchar check ("frequency" in ('daily', 'weekly', 'monthly', 'yearly', 'once')) not null, "scheduled_at" datetime not null, "next_execution_at" datetime not null, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade, foreign key("from_wallet_id") references "wallets"("id") on delete cascade, foreign key("to_wallet_id") references "wallets"("id") on delete cascade);

-- table: sessions
CREATE TABLE "sessions" ("id" varchar not null, "user_id" integer, "ip_address" varchar, "user_agent" text, "payload" text not null, "last_activity" integer not null, primary key ("id"));

-- table: support_messages
CREATE TABLE "support_messages" ("id" integer primary key autoincrement not null, "support_thread_id" integer not null, "sender_type" varchar not null, "sender_id" integer not null, "message" text, "file_url" varchar, "created_at" datetime, "updated_at" datetime, foreign key("support_thread_id") references "support_threads"("id") on delete cascade);

-- table: support_threads
CREATE TABLE "support_threads" ("id" integer primary key autoincrement not null, "user_id" integer not null, "admin_id" integer, "issue_type" varchar not null, "priority" varchar not null default 'medium', "status" varchar not null default 'open', "tx_id" varchar, "created_at" datetime, "updated_at" datetime, "subject" varchar not null default 'Support Ticket', "category" varchar, foreign key("user_id") references "users"("id") on delete cascade, foreign key("admin_id") references "users"("id") on delete set null);

-- table: transactions
CREATE TABLE "transactions" ("id" integer primary key autoincrement not null, "user_id" integer not null, "from_wallet_id" integer, "to_wallet_id" integer, "type" varchar check ("type" in ('deposit', 'withdraw', 'transfer', 'receive')) not null, "amount" numeric not null, "reference" varchar not null, "description" text, "status" varchar check ("status" in ('pending', 'completed', 'failed', 'cancelled')) not null default 'pending', "metadata" text, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade, foreign key("from_wallet_id") references "wallets"("id") on delete set null, foreign key("to_wallet_id") references "wallets"("id") on delete set null);

-- table: user_notifications
CREATE TABLE "user_notifications" ("id" integer primary key autoincrement not null, "user_id" integer not null, "type" varchar not null, "title" varchar not null, "message" text not null, "is_read" tinyint(1) not null default '0', "metadata" text, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade);

-- table: users
CREATE TABLE "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "email_verified_at" datetime, "password" varchar not null, "phone" varchar, "role" varchar check ("role" in ('user', 'admin', 'master_distributor', 'distributor', 'retailer')) not null default 'user', "is_active" tinyint(1) not null default ('1'), "remember_token" varchar, "created_at" datetime, "updated_at" datetime, "distributor_id" integer, "date_of_birth" date, "bank_account_name" varchar, "bank_account_number" varchar, "bank_ifsc_code" varchar, "bank_name" varchar, "kyc_document_path" varchar, "kyc_status" varchar not null default ('pending'), "withdraw_otp_code" varchar, "withdraw_otp_expires_at" datetime, "last_name" varchar, "alternate_mobile" varchar, "business_name" varchar, "address" text, "city" varchar, "state" varchar, "profile_photo_path" varchar, "kyc_id_number" varchar, "kyc_photo_path" varchar, "address_proof_front_path" varchar, "address_proof_back_path" varchar, "kyc_document_type" varchar, "kyc_selfie_path" varchar, "kyc_liveness_verified" tinyint(1) not null default '0', "plain_password" varchar, "pan_number" varchar, "pan_proof_front_path" varchar, "pan_proof_back_path" varchar);

-- table: users_commission
CREATE TABLE "users_commission" ("id" integer primary key autoincrement not null, "user_id" integer not null, "user_name" varchar not null, "agent_id" varchar not null, "total_commission" numeric not null default '0', "withdrawal_commission" numeric not null default '0', "available_commission" numeric not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade);

-- table: wallet_adjustments
CREATE TABLE "wallet_adjustments" ("id" integer primary key autoincrement not null, "admin_user_id" integer not null, "user_id" integer not null, "wallet_id" integer not null, "type" varchar check ("type" in ('add', 'deduct', 'force_settlement')) not null, "amount" numeric not null, "reference" varchar not null, "remarks" text, "created_at" datetime, "updated_at" datetime, foreign key("admin_user_id") references "users"("id") on delete cascade, foreign key("user_id") references "users"("id") on delete cascade, foreign key("wallet_id") references "wallets"("id") on delete cascade);

-- table: wallet_limits
CREATE TABLE "wallet_limits" ("id" integer primary key autoincrement not null, "user_id" integer not null, "limit_type" varchar check ("limit_type" in ('daily', 'monthly', 'per_transaction')) not null, "max_amount" numeric not null, "transaction_count" integer not null default '0', "total_amount" numeric not null default '0', "reset_date" date, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade);

-- table: wallets
CREATE TABLE "wallets" ("id" integer primary key autoincrement not null, "user_id" integer not null, "name" varchar not null, "type" varchar check ("type" in ('main', 'sub')) not null default 'main', "balance" numeric not null default '0', "is_frozen" tinyint(1) not null default '0', "freeze_reason" text, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade);

-- table: withdraw_requests
CREATE TABLE "withdraw_requests" ("id" integer primary key autoincrement not null, "user_id" integer not null, "wallet_id" integer not null, "amount" numeric not null, "net_amount" numeric not null default '0', "status" varchar check ("status" in ('pending', 'approved', 'rejected', 'processed')) not null default 'pending', "remarks" text, "reviewed_by" integer, "reviewed_at" datetime, "metadata" text, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade, foreign key("wallet_id") references "wallets"("id") on delete cascade, foreign key("reviewed_by") references "users"("id") on delete set null);

-- index: admin_settings_key_unique
CREATE UNIQUE INDEX "admin_settings_key_unique" on "admin_settings" ("key");

-- index: cache_expiration_index
CREATE INDEX "cache_expiration_index" on "cache" ("expiration");

-- index: cache_locks_expiration_index
CREATE INDEX "cache_locks_expiration_index" on "cache_locks" ("expiration");

-- index: comm_txn_orig_type_idx
CREATE INDEX "comm_txn_orig_type_idx" on "commission_transactions" ("original_transaction_id", "commission_type");

-- index: comm_txn_user_type_idx
CREATE INDEX "comm_txn_user_type_idx" on "commission_transactions" ("user_id", "commission_type");

-- index: commission_configs_user_role_is_active_index
CREATE INDEX "commission_configs_user_role_is_active_index" on "commission_configs" ("user_role", "is_active");

-- index: commission_overrides_user_id_unique
CREATE UNIQUE INDEX "commission_overrides_user_id_unique" on "commission_overrides" ("user_id");

-- index: commission_transactions_reference_unique
CREATE UNIQUE INDEX "commission_transactions_reference_unique" on "commission_transactions" ("reference");

-- index: failed_jobs_uuid_unique
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs" ("uuid");

-- index: jobs_queue_index
CREATE INDEX "jobs_queue_index" on "jobs" ("queue");

-- index: personal_access_tokens_expires_at_index
CREATE INDEX "personal_access_tokens_expires_at_index" on "personal_access_tokens" ("expires_at");

-- index: personal_access_tokens_token_unique
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens" ("token");

-- index: personal_access_tokens_tokenable_type_tokenable_id_index
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens" ("tokenable_type", "tokenable_id");

-- index: sessions_last_activity_index
CREATE INDEX "sessions_last_activity_index" on "sessions" ("last_activity");

-- index: sessions_user_id_index
CREATE INDEX "sessions_user_id_index" on "sessions" ("user_id");

-- index: support_messages_sender_type_sender_id_index
CREATE INDEX "support_messages_sender_type_sender_id_index" on "support_messages" ("sender_type", "sender_id");

-- index: support_messages_support_thread_id_index
CREATE INDEX "support_messages_support_thread_id_index" on "support_messages" ("support_thread_id");

-- index: support_threads_admin_id_index
CREATE INDEX "support_threads_admin_id_index" on "support_threads" ("admin_id");

-- index: support_threads_user_id_index
CREATE INDEX "support_threads_user_id_index" on "support_threads" ("user_id");

-- index: transactions_reference_unique
CREATE UNIQUE INDEX "transactions_reference_unique" on "transactions" ("reference");

-- index: users_commission_agent_id_index
CREATE INDEX "users_commission_agent_id_index" on "users_commission" ("agent_id");

-- index: users_commission_user_id_unique
CREATE UNIQUE INDEX "users_commission_user_id_unique" on "users_commission" ("user_id");

-- index: users_email_unique
CREATE UNIQUE INDEX "users_email_unique" on "users" ("email");

-- index: wallet_adjustments_reference_unique
CREATE UNIQUE INDEX "wallet_adjustments_reference_unique" on "wallet_adjustments" ("reference");
