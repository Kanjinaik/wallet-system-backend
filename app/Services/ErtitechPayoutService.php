<?php

namespace App\Services;

use App\Models\AdminSetting;
use App\Models\User;
use App\Models\WithdrawRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ErtitechPayoutService
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $merchantId;
    private string $walletId;
    private string $aesKey;
    private string $preferredBank;
    private string $mode;

    public function __construct(array $overrides = [])
    {
        $config = array_merge([
            'mode' => trim((string) AdminSetting::getFirstValue([
                'gateway_ertitech_mode',
                'sys_gateway_ertitech_mode',
            ], (string) config('services.ertitech_payout.mode', 'test'))),
            'base_url' => trim((string) config('services.ertitech_payout.base_url', '')),
            'username' => trim((string) AdminSetting::getFirstValue([
                'gateway_ertitech_username',
                'sys_gateway_ertitech_username',
            ], (string) config('services.ertitech_payout.username', ''))),
            'password' => trim((string) AdminSetting::getFirstValue([
                'gateway_ertitech_password',
                'sys_gateway_ertitech_password',
            ], (string) config('services.ertitech_payout.password', ''))),
            'merchant_id' => trim((string) AdminSetting::getFirstValue([
                'gateway_ertitech_merchant_id',
                'sys_gateway_ertitech_merchant_id',
            ], (string) config('services.ertitech_payout.merchant_id', ''))),
            'wallet_id' => trim((string) AdminSetting::getFirstValue([
                'gateway_ertitech_wallet_id',
                'sys_gateway_ertitech_wallet_id',
            ], (string) config('services.ertitech_payout.wallet_id', ''))),
            'aes_key' => trim((string) AdminSetting::getFirstValue([
                'gateway_ertitech_aes_key',
                'sys_gateway_ertitech_aes_key',
            ], (string) config('services.ertitech_payout.aes_key', ''))),
            'preferred_bank' => trim((string) config('services.ertitech_payout.preferred_bank', 'pnb')),
        ], $overrides);

        if (($config['base_url'] ?? '') === '') {
            $config['base_url'] = strtolower((string) ($config['mode'] ?? 'test')) === 'live'
                ? 'https://api.ertipay.com/payout'
                : 'https://api.ertipay.com/uat';
        }

        $this->baseUrl = rtrim((string) $config['base_url'], '/');
        $this->username = trim((string) $config['username']);
        $this->password = trim((string) $config['password']);
        $this->merchantId = trim((string) $config['merchant_id']);
        $this->walletId = trim((string) $config['wallet_id']);
        $this->aesKey = trim((string) $config['aes_key']);
        $this->preferredBank = trim((string) $config['preferred_bank']);
        $this->mode = trim((string) $config['mode']);
    }

    public function isConfigured(): bool
    {
        return $this->username !== ''
            && $this->password !== ''
            && $this->merchantId !== ''
            && $this->aesKey !== '';
    }

    public function createPayout(WithdrawRequest $withdrawRequest, User $user, float $amount): array
    {
        if (!$this->isConfigured()) {
            return [
                'mode' => 'internal',
                'status' => 'pending',
            ];
        }

        $bankAccount = trim((string) ($withdrawRequest->metadata['bank_account'] ?? ''));
        $ifscCode = trim((string) ($withdrawRequest->metadata['ifsc_code'] ?? ''));
        $accountHolderName = trim((string) ($withdrawRequest->metadata['account_holder_name'] ?? $user->name));
        if ($bankAccount === '' || $ifscCode === '' || $accountHolderName === '') {
            throw new \RuntimeException('Bank details are missing for Ertitech payout.');
        }

        $custUniqRef = substr('WDR' . $withdrawRequest->id . strtoupper(Str::random(16)), 0, 40);
        $beneficiaryMobile = $this->normalizePhone((string) ($withdrawRequest->metadata['beneficiary_mobile'] ?? $user->phone ?? ''));
        $accountType = strtoupper((string) ($withdrawRequest->metadata['account_type'] ?? 'SB'));
        if (!in_array($accountType, ['SB', 'CA'], true)) {
            $accountType = 'SB';
        }
        if ($this->isTestMode()) {
            return $this->createMockPayout($custUniqRef, $amount, $bankAccount, $ifscCode, $accountHolderName, $beneficiaryMobile);
        }

        $plainPayload = [
            'paymentDetails' => [
                'txnPaymode' => 'IMPS',
                'txnAmount' => number_format($amount, 2, '.', ''),
                'beneIfscCode' => strtoupper($ifscCode),
                'beneAccNum' => $bankAccount,
                'beneName' => $accountHolderName,
                'custUniqRef' => $custUniqRef,
                'beneMobileNo' => $beneficiaryMobile,
                'accountType' => $accountType,
                'preferredBank' => $this->preferredBank !== '' ? strtolower($this->preferredBank) : 'pnb',
            ],
        ];

        [$body, $endpointUsed] = $this->submitPayoutRequest(
            $plainPayload,
            $bankAccount,
            $ifscCode,
            $beneficiaryMobile
        );

        if (($body['success'] ?? false) !== true && isset($body['message'])) {
            throw new \RuntimeException((string) $body['message']);
        }

        $decrypted = [];
        $encryptedResponse = (string) data_get($body, 'data.encryptedResponseData', '');
        if ($encryptedResponse !== '') {
            $decrypted = $this->decryptPayload($encryptedResponse);
        } elseif (is_array(data_get($body, 'data.response'))) {
            $decrypted = (array) data_get($body, 'data.response');
        }

        $statusResponse = [];
        $txnStatus = strtoupper((string) data_get($decrypted, 'txn_status.transactionStatus', data_get($body, 'data.status', 'PENDING')));
        if (in_array($txnStatus, ['', 'PENDING', 'TIMEOUT'], true)) {
            $statusResponse = $this->fetchStatusSafely($custUniqRef);
            if ($statusResponse !== []) {
                $decrypted = is_array(data_get($statusResponse, 'data.response'))
                    ? (array) data_get($statusResponse, 'data.response')
                    : $decrypted;
                $txnStatus = strtoupper((string) data_get($decrypted, 'txn_status.transactionStatus', data_get($statusResponse, 'data.status', $txnStatus)));
            }
        }

        return [
            'mode' => 'ertitech_payout',
            'status' => $txnStatus !== '' ? $txnStatus : 'PENDING',
            'cust_uniq_ref' => $custUniqRef,
            'order_id' => data_get($decrypted, 'orderId'),
            'crn' => data_get($decrypted, 'crn'),
            'utr' => data_get($decrypted, 'txn_status.utrNo', data_get($decrypted, 'txn_status.utr')),
            'wallet_id' => $this->walletId,
            'endpoint' => $endpointUsed,
            'raw_response' => $body,
            'status_response' => $statusResponse,
            'decrypted_response' => $decrypted,
        ];
    }

    public function getPayoutProcessingState(array $payoutMeta): string
    {
        $status = strtoupper(trim((string) ($payoutMeta['status'] ?? '')));

        if (in_array($status, ['PAID', 'SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'COMPLETE', 'PROCESSED'], true)) {
            return 'completed';
        }

        if (in_array($status, ['FAILED', 'FAILURE', 'REJECTED', 'REVERSED', 'CANCELLED', 'CANCELED'], true)) {
            return 'failed';
        }

        return 'pending';
    }

    public function getFailureMessage(array $payoutMeta): string
    {
        return trim((string) (
            data_get($payoutMeta, 'decrypted_response.txn_status.statusDescription')
            ?: data_get($payoutMeta, 'status_response.message')
            ?: data_get($payoutMeta, 'raw_response.message')
            ?: data_get($payoutMeta, 'fallback_reason')
            ?: 'Payout was rejected by the bank gateway.'
        ));
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Ertitech credentials are incomplete.');
        }

        if ($this->isTestMode()) {
            return [
                'success' => true,
                'message' => 'Ertitech test mode is active with simulated payouts.',
                'base_url' => $this->baseUrl,
                'mode' => $this->mode,
                'merchant_id' => $this->merchantId,
                'token_preview' => 'mock-test-token...',
                'balance_response' => [
                    'success' => true,
                    'data' => [
                        'balance' => [
                            $this->preferredBank !== '' ? strtolower($this->preferredBank) : 'pnb' => [
                                'balance' => 999999.99,
                                'minBalance' => 0,
                            ],
                        ],
                        'creationDateTime' => now()->toIso8601String(),
                    ],
                    'message' => 'Simulated balance fetched successfully.',
                    'errors' => null,
                    'exception' => null,
                ],
            ];
        }

        $token = $this->getToken();
        $balanceBody = $this->getBalance();

        return [
            'success' => true,
            'message' => 'Ertitech connection successful.',
            'base_url' => $this->baseUrl,
            'mode' => $this->mode,
            'merchant_id' => $this->merchantId,
            'token_preview' => substr($token, 0, 12) . '...',
            'balance_response' => $balanceBody,
        ];
    }

    private function authenticatedHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'merchantid' => $this->merchantId,
            'Authorization' => 'Bearer ' . $this->getToken(),
        ];
    }

    private function payoutHeaders(string $bankAccount, string $ifscCode, string $beneficiaryMobile): array
    {
        return array_merge($this->authenticatedHeaders(), [
            'cubaccno' => $bankAccount,
            'cubifsc' => strtoupper($ifscCode),
            'cubmobnum' => $beneficiaryMobile,
        ]);
    }

    private function submitPayoutRequest(array $plainPayload, string $bankAccount, string $ifscCode, string $beneficiaryMobile): array
    {
        $headers = $this->payoutHeaders($bankAccount, $ifscCode, $beneficiaryMobile);
        $requestBody = [
            'data' => $this->encryptPayload($plainPayload),
        ];

        $endpoints = [
            '/cub/connectedbank',
            '/fund',
        ];

        $lastMessage = 'Ertitech fund transfer failed.';
        foreach ($endpoints as $endpoint) {
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->baseUrl . $endpoint, $requestBody);

            $body = is_array($response->json()) ? $response->json() : [];
            if ($response->successful()) {
                return [$body, $endpoint];
            }

            $lastMessage = (string) ($body['message'] ?? $body['error'] ?? $lastMessage);

            if (in_array($response->status(), [401, 403, 422], true)) {
                throw new \RuntimeException($lastMessage);
            }
        }

        throw new \RuntimeException($lastMessage);
    }

    private function getToken(): string
    {
        $cacheKey = 'ertitech_payout_token_' . md5($this->merchantId . '|' . $this->username);

        return Cache::remember($cacheKey, now()->addMinutes(55), function () {
            $attempts = array_values(array_filter([
                ['email' => $this->username, 'password' => $this->password],
                ['email' => $this->merchantId, 'password' => $this->password],
                ['username' => $this->username, 'password' => $this->password],
                ['username' => $this->merchantId, 'password' => $this->password],
                ['merchantId' => $this->merchantId, 'password' => $this->password],
                ['merchantid' => $this->merchantId, 'password' => $this->password],
            ], fn (array $payload): bool => count(array_filter($payload, fn ($value) => trim((string) $value) !== '')) === 2));

            $lastMessage = 'Ertitech login failed.';
            foreach ($attempts as $payload) {
                $response = Http::timeout(30)->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'merchantid' => $this->merchantId,
                ])->post($this->baseUrl . '/login', $payload);

                $body = is_array($response->json()) ? $response->json() : [];
                if ($response->successful() && ($body['success'] ?? false) === true) {
                    $token = trim((string) data_get($body, 'data.token', ''));
                    if ($token === '') {
                        throw new \RuntimeException('Ertitech token missing from login response.');
                    }

                    return $token;
                }

                $lastMessage = (string) ($body['message'] ?? $body['error'] ?? $lastMessage);
            }

            throw new \RuntimeException($lastMessage);
        });
    }

    private function getBalance(): array
    {
        $response = Http::timeout(30)->withHeaders($this->authenticatedHeaders())
            ->get($this->baseUrl . '/balance');

        $body = is_array($response->json()) ? $response->json() : [];
        if (!$response->successful() || ($body['success'] ?? false) !== true) {
            $message = (string) ($body['message'] ?? $body['error'] ?? 'Unable to fetch Ertitech balance.');
            throw new \RuntimeException($message);
        }

        return $body;
    }

    private function fetchStatusSafely(string $custUniqRef): array
    {
        try {
            return $this->fetchStatus($custUniqRef);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fetchStatus(string $custUniqRef): array
    {
        $response = Http::timeout(30)->withHeaders($this->authenticatedHeaders())
            ->post($this->baseUrl . '/status', [
                'custUniqRef' => $custUniqRef,
            ]);

        $body = is_array($response->json()) ? $response->json() : [];
        if (!$response->successful() || ($body['success'] ?? false) !== true) {
            $message = (string) ($body['message'] ?? $body['error'] ?? 'Unable to fetch Ertitech transaction status.');
            throw new \RuntimeException($message);
        }

        return $body;
    }

    private function encryptPayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Unable to encode Ertitech payout payload.');
        }

        $key = @hex2bin($this->aesKey);
        if ($key === false || strlen($key) !== 16) {
            throw new \RuntimeException('Invalid Ertitech AES key. It must be a 128-bit hex string.');
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($json, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Failed to encrypt Ertitech payout payload.');
        }

        return bin2hex($iv) . ':' . bin2hex($encrypted);
    }

    private function decryptPayload(string $payload): array
    {
        $parts = explode(':', $payload, 2);
        if (count($parts) !== 2) {
            throw new \RuntimeException('Invalid encrypted response format from Ertitech.');
        }

        $key = @hex2bin($this->aesKey);
        $iv = @hex2bin($parts[0]);
        $ciphertext = @hex2bin($parts[1]);
        if ($key === false || $iv === false || $ciphertext === false) {
            throw new \RuntimeException('Failed to decode Ertitech encrypted response.');
        }

        $decrypted = openssl_decrypt($ciphertext, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new \RuntimeException('Failed to decrypt Ertitech response payload.');
        }

        $decoded = json_decode($decrypted, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if (!$digits || strlen($digits) < 10) {
            return '9999999999';
        }

        if (strlen($digits) > 10) {
            return substr($digits, -10);
        }

        return $digits;
    }

    private function isTestMode(): bool
    {
        return strtolower($this->mode) === 'test';
    }

    private function createMockPayout(
        string $custUniqRef,
        float $amount,
        string $bankAccount,
        string $ifscCode,
        string $accountHolderName,
        string $beneficiaryMobile
    ): array {
        $orderId = 'T' . now()->format('ymdHis') . random_int(1000, 9999);
        $crn = Str::lower(Str::random(30));
        $utr = 'MOCK' . now()->format('His') . random_int(100000, 999999);
        $preferredBank = $this->preferredBank !== '' ? strtolower($this->preferredBank) : 'pnb';

        $decrypted = [
            'orderId' => $orderId,
            'merchantId' => $this->merchantId,
            'crn' => $crn,
            'txn_status' => [
                'statusDescription' => 'Simulated Ertitech test payout processed successfully.',
                'responseCode' => '00',
                'utrNo' => $utr,
                'transactionStatus' => 'PAID',
            ],
            'amount' => round($amount, 2),
            'txnAmount' => round($amount, 2),
            'totalCharge' => 0,
            'tax' => 0,
            'totalDeduction' => 0,
            'paymentType' => 'IMPS',
            'custUniqRef' => $custUniqRef,
            'preferredBank' => $preferredBank,
            'beneAccNum' => $bankAccount,
            'beneIfscCode' => strtoupper($ifscCode),
            'beneName' => $accountHolderName,
            'beneMobileNo' => $beneficiaryMobile,
            'reason' => 'TEST_MODE_SIMULATION',
        ];

        return [
            'mode' => 'ertitech_payout',
            'status' => 'PAID',
            'cust_uniq_ref' => $custUniqRef,
            'order_id' => $orderId,
            'crn' => $crn,
            'utr' => $utr,
            'wallet_id' => $this->walletId,
            'raw_response' => [
                'success' => true,
                'data' => [
                    'status' => 'SUCCESS',
                    'response' => $decrypted,
                    'creationDateTime' => now()->toIso8601String(),
                ],
                'message' => 'Simulated payout processed in test mode.',
                'errors' => null,
                'exception' => null,
            ],
            'status_response' => [
                'success' => true,
                'data' => [
                    'status' => 'SUCCESS',
                    'response' => $decrypted,
                    'creationDateTime' => now()->toIso8601String(),
                ],
                'message' => 'Simulated transaction status retrieved successfully.',
                'errors' => null,
                'exception' => null,
            ],
            'decrypted_response' => $decrypted,
        ];
    }
}
