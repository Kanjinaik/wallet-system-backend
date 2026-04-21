<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\BillAvenueBbpsService;
use App\Services\RetailerRechargeStatusService;
use Illuminate\Console\Command;

class SyncPendingRecharges extends Command
{
    protected $signature = 'recharge:sync-pending {--minutes=15} {--limit=100}';
    protected $description = 'Synchronize pending retailer prepaid recharge transactions with the configured PayU provider';

    public function handle(BillAvenueBbpsService $bbps, RetailerRechargeStatusService $statusService): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, (int) $this->option('limit'));
        $cutoff = now()->subMinutes($minutes);

        $candidates = Transaction::query()
            ->where('type', 'recharge')
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->filter(function (Transaction $transaction) {
                $requestId = trim((string) ($transaction->metadata['provider_request_id'] ?? ''));
                if ($requestId === '') {
                    return false;
                }

                $lastSyncAt = $transaction->metadata['last_recharge_status_sync_at'] ?? null;
                if (!$lastSyncAt) {
                    return true;
                }

                try {
                    return now()->diffInMinutes($lastSyncAt) >= 5;
                } catch (\Throwable) {
                    return true;
                }
            })
            ->values();

        $synced = 0;
        $completed = 0;
        $failed = 0;
        $errors = 0;

        foreach ($candidates as $transaction) {
            $requestId = (string) ($transaction->metadata['provider_request_id'] ?? '');

            try {
                $response = $bbps->syncStatusByRequestId($requestId);
                $updated = $statusService->applyProviderPayload($transaction, $response, 'scheduled_status_sync_response');
                $synced++;

                if ($updated->status === 'completed') {
                    $completed++;
                } elseif ($updated->status === 'failed') {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $errors++;
                $statusService->recordSyncFailure($transaction, $e, 'scheduled_status_sync_error');
                $this->warn('Recharge sync failed for transaction #' . $transaction->id . ': ' . $e->getMessage());
            }
        }

        $this->info('Pending recharge sync complete.');
        $this->line('Checked: ' . $candidates->count());
        $this->line('Synced: ' . $synced);
        $this->line('Completed: ' . $completed);
        $this->line('Failed: ' . $failed);
        $this->line('Errors: ' . $errors);

        return Command::SUCCESS;
    }
}
