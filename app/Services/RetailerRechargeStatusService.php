<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class RetailerRechargeStatusService
{
    public function __construct(private BillAvenueBbpsService $bbps)
    {
    }

    public function applyProviderPayload(Transaction $transaction, array $payload, string $metadataKey = 'provider_status_payload'): Transaction
    {
        $transaction = $transaction->fresh() ?? $transaction;
        $metadata = $transaction->metadata ?? [];
        $statusPayload = $this->extractStatusPayload($payload);
        $normalizedStatus = $this->bbps->normalizeGatewayStatus($statusPayload);

        $metadata[$metadataKey] = $payload;
        $metadata['last_recharge_status_sync_at'] = now()->toIso8601String();
        $metadata['provider_request_id'] = $metadata['provider_request_id']
            ?? $this->firstNonEmpty([
                $payload['requestId'] ?? null,
                $payload['request_id'] ?? null,
                $payload['statusRequestId'] ?? null,
            ]);
        $metadata['provider_txn_ref_id'] = $metadata['provider_txn_ref_id']
            ?? $this->firstNonEmpty([
                $statusPayload['txnRefId'] ?? null,
                $statusPayload['txnReferenceId'] ?? null,
                $payload['txnRefId'] ?? null,
            ]);
        $metadata['provider_approval_ref_number'] = $metadata['provider_approval_ref_number']
            ?? $this->firstNonEmpty([
                $statusPayload['approvalRefNumber'] ?? null,
                $payload['approvalRefNumber'] ?? null,
            ]);
        $metadata['provider_final_status'] = $normalizedStatus;

        $transaction->metadata = $metadata;
        $transaction->status = match ($normalizedStatus) {
            'completed' => 'completed',
            'failed' => 'failed',
            default => 'pending',
        };
        $transaction->save();

        if ($normalizedStatus === 'failed') {
            $this->refundWalletForFailure($transaction);
            $transaction->refresh();
        }

        return $transaction;
    }

    public function refundWalletForFailure(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->first();
            if (!$locked) {
                return;
            }

            $metadata = $locked->metadata ?? [];
            if (($metadata['wallet_refunded'] ?? false) === true) {
                return;
            }

            $wallet = $locked->fromWallet()->lockForUpdate()->first();
            if ($wallet) {
                $wallet->balance = round((float) $wallet->balance + (float) $locked->amount, 2);
                $wallet->save();
            }

            $metadata['wallet_refunded'] = true;
            $metadata['wallet_refunded_at'] = now()->toIso8601String();
            $locked->metadata = $metadata;
            $locked->save();
        });
    }

    public function locateTransaction(array $payload): ?Transaction
    {
        $requestId = trim((string) $this->firstNonEmpty([
            $payload['requestId'] ?? null,
            $payload['request_id'] ?? null,
            $payload['statusRequestId'] ?? null,
        ]));
        $txnRefId = trim((string) $this->firstNonEmpty([
            $payload['txnRefId'] ?? null,
            $payload['txnReferenceId'] ?? null,
        ]));

        if ($requestId !== '') {
            $transaction = Transaction::where('type', 'recharge')
                ->where('metadata->provider_request_id', $requestId)
                ->latest('id')
                ->first();
            if ($transaction) {
                return $transaction;
            }
        }

        if ($txnRefId !== '') {
            $transaction = Transaction::where('type', 'recharge')
                ->where('metadata->provider_txn_ref_id', $txnRefId)
                ->latest('id')
                ->first();
            if ($transaction) {
                return $transaction;
            }
        }

        return null;
    }

    public function recordSyncFailure(Transaction $transaction, \Throwable $e, string $metadataKey = 'status_sync_error'): void
    {
        $metadata = $transaction->metadata ?? [];
        $metadata[$metadataKey] = $e->getMessage();
        $metadata['last_recharge_status_sync_at'] = now()->toIso8601String();
        $transaction->metadata = $metadata;
        $transaction->save();
    }

    private function extractStatusPayload(array $payload): array
    {
        $txn = $payload['txnList'][0] ?? null;

        return is_array($txn) ? $txn : $payload;
    }

    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            $candidate = trim((string) ($value ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
