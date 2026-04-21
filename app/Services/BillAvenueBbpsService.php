<?php

namespace App\Services;

use App\Models\AdminSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BillAvenueBbpsService
{
    private string $provider;
    private string $baseUrl;
    private string $accessCode;
    private string $secretKey;
    private string $instituteId;
    private string $workingKey;
    private string $iv;
    private string $agentId;
    private string $authBaseUrl;
    private string $grantType;
    private string $scope;
    private string $mode;
    private string $channel;
    private int $timeoutSeconds;

    public function __construct(array $overrides = [])
    {
        $savedProvider = AdminSetting::getValue('sys_gateway_recharge_provider', (string) config('services.retailer_recharge.provider', ''));
        $savedBaseUrl = AdminSetting::getValue('sys_gateway_recharge_base_url', (string) config('services.retailer_recharge.base_url', 'https://bbps-sb.payu.in'));
        $savedApiKey = AdminSetting::getValue('sys_gateway_recharge_api_key', (string) config('services.retailer_recharge.api_key', ''));
        $savedUsername = AdminSetting::getValue('sys_gateway_recharge_username', (string) config('services.retailer_recharge.username', ''));
        $savedPassword = AdminSetting::getValue('sys_gateway_recharge_password', (string) config('services.retailer_recharge.password', ''));
        $savedWorkingKey = AdminSetting::getValue('sys_gateway_recharge_working_key', (string) config('services.retailer_recharge.working_key', ''));
        $savedIv = AdminSetting::getValue('sys_gateway_recharge_iv', (string) config('services.retailer_recharge.iv', ''));
        $savedAgentId = AdminSetting::getValue('sys_gateway_recharge_agent_id', (string) config('services.retailer_recharge.agent_id', ''));
        $savedAuthBaseUrl = AdminSetting::getValue('sys_gateway_recharge_auth_base_url', (string) config('services.retailer_recharge.auth_base_url', ''));
        $savedGrantType = AdminSetting::getValue('sys_gateway_recharge_grant_type', (string) config('services.retailer_recharge.grant_type', 'client_credentials'));
        $savedScope = AdminSetting::getValue('sys_gateway_recharge_scope', (string) config('services.retailer_recharge.scope', ''));
        $savedMode = AdminSetting::getValue('sys_gateway_recharge_mode', (string) config('services.retailer_recharge.mode', 'test'));
        $packedSecret = trim((string) AdminSetting::getValue('sys_gateway_recharge_secret_key', (string) config('services.retailer_recharge.secret_key', '')));
        $savedPayuKey = trim((string) AdminSetting::getValue('sys_gateway_payu_key', env('PAYU_MERCHANT_KEY', '')));
        $savedPayuSalt = trim((string) AdminSetting::getValue('sys_gateway_payu_salt', env('PAYU_MERCHANT_SALT', '')));
        $savedPayuMode = trim((string) AdminSetting::getValue('sys_gateway_payu_mode', env('PAYU_MODE', 'test')));
        [$derivedWorkingKey, $derivedIv] = array_pad(explode('|', $packedSecret, 2), 2, '');
        $providerLooksPayu = $this->isPayuProviderName($savedProvider);

        if ($providerLooksPayu) {
            // For PayU prepaid recharge, use dedicated recharge OAuth credentials first.
            // Fallback to payu_key/salt only if recharge fields are blank.
            if (trim((string) $savedApiKey) === '' && $savedPayuKey !== '') {
                $savedApiKey = $savedPayuKey;
            }
            if ($packedSecret === '' && $savedPayuSalt !== '') {
                $packedSecret = $savedPayuSalt;
            }
            $normalizedPayuMode = strtolower(trim((string) $savedPayuMode));
            $normalizedRechargeMode = strtolower(trim((string) $savedMode));
            if (in_array($normalizedPayuMode, ['test', 'live'], true)) {
                if ($normalizedRechargeMode === '' || ($normalizedRechargeMode === 'test' && $normalizedPayuMode === 'live')) {
                    $savedMode = $normalizedPayuMode;
                }
            }
        }

        $config = array_merge([
            'provider' => $savedProvider,
            'base_url' => $savedBaseUrl,
            'access_code' => $savedApiKey,
            'institute_id' => $savedUsername,
            'working_key' => $savedWorkingKey !== '' ? $savedWorkingKey : ($derivedWorkingKey !== '' ? $derivedWorkingKey : $packedSecret),
            'iv' => $savedIv !== '' ? $savedIv : $derivedIv,
            'agent_id' => $savedAgentId,
            'auth_base_url' => $savedAuthBaseUrl,
            'grant_type' => $savedGrantType !== '' ? $savedGrantType : 'client_credentials',
            'scope' => $savedScope,
            'mode' => $savedMode,
            'channel' => config('services.retailer_recharge.channel', 'INT'),
            'timeout_seconds' => config('services.retailer_recharge.timeout_seconds', 300),
        ], $overrides);

        $this->provider = strtolower(trim((string) $config['provider']));
        $resolvedBaseUrl = rtrim((string) $config['base_url'], '/');
        $this->accessCode = trim((string) $config['access_code']);
        $this->secretKey = $packedSecret;
        $this->instituteId = trim((string) $config['institute_id']);
        $this->workingKey = trim((string) $config['working_key']);
        $this->iv = trim((string) $config['iv']);
        $this->agentId = trim((string) $config['agent_id']);
        $resolvedAuthBaseUrl = rtrim((string) $config['auth_base_url'], '/');
        $this->grantType = trim((string) $config['grant_type']);
        $this->scope = trim((string) $config['scope']);
        $this->mode = strtolower(trim((string) $config['mode']));
        $this->channel = strtoupper(trim((string) $config['channel']));
        $this->timeoutSeconds = max(30, (int) $config['timeout_seconds']);

        if ($this->isPayuProviderName($this->provider)) {
            $resolvedBaseUrl = $resolvedBaseUrl !== '' ? $resolvedBaseUrl : $this->defaultPayuApiBaseUrl();
            $resolvedAuthBaseUrl = $resolvedAuthBaseUrl !== '' ? $resolvedAuthBaseUrl : $this->defaultPayuAuthBaseUrl($resolvedBaseUrl);
        }

        $this->baseUrl = $resolvedBaseUrl;
        $this->authBaseUrl = $resolvedAuthBaseUrl;
    }

    public function isEnabled(): bool
    {
        $provider = preg_replace('/[^a-z0-9]+/', '', strtolower($this->provider));
        return $provider === 'payu' || str_contains(strtolower($this->baseUrl), 'payu');
    }

    public function isConfigured(): bool
    {
        if ($this->isPayuProvider()) {
            return $this->accessCode !== ''
                && $this->secretKey !== ''
                && $this->agentId !== ''
                && $this->baseUrl !== ''
                && ($this->authBaseUrl !== '' || $this->isTestMode());
        }

        return false;
    }

    public function isTestMode(): bool
    {
        return $this->mode === 'test';
    }

    public function connectionSummary(): array
    {
        $enabled = $this->isEnabled();
        $configured = $this->isConfigured();
        $normalizedProvider = trim((string) $this->provider);
        $providerLabel = $normalizedProvider !== '' ? $normalizedProvider : 'not_set';
        $mode = $this->isTestMode() ? 'test' : 'live';
        $status = !$enabled
            ? 'not_configured'
            : (!$configured ? 'credentials_missing' : ($mode === 'live' ? 'live_ready' : 'test_ready'));

        return [
            'provider' => $providerLabel,
            'base_url' => $this->baseUrl,
            'mode' => $mode,
            'channel' => $this->channel,
            'enabled' => $enabled,
            'configured' => $configured,
            'auth_base_url' => $this->authBaseUrl,
            'status' => $status,
            'status_label' => match ($status) {
                'live_ready' => 'Live',
                'test_ready' => 'Test',
                'credentials_missing' => 'Not Ready',
                default => 'Disabled',
            },
            'can_attempt_realtime' => $enabled && $configured && $mode === 'live',
            'missing' => array_values(array_filter([
                $this->accessCode === '' ? 'access_code' : null,
                $this->isPayuProvider() && $this->secretKey === '' ? 'client_secret' : null,
                $this->isPayuProvider() ? null : ($this->instituteId === '' ? 'institute_id' : null),
                $this->isPayuProvider() ? null : ($this->workingKey === '' ? 'working_key' : null),
                $this->isPayuProvider() ? null : ($this->iv === '' ? 'iv' : null),
                $this->agentId === '' ? 'agent_id' : null,
                $this->isPayuProvider() && $this->baseUrl === '' ? 'base_url' : null,
                $this->isPayuProvider() && !$this->isTestMode() && $this->authBaseUrl === '' ? 'auth_base_url' : null,
            ])),
        ];
    }

    public function submitRecharge(array $payload, User $user, Request $request): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Retailer recharge provider is not configured.');
        }

        if ($this->isTestMode() && !$this->isPayuProvider()) {
            return $this->mockRecharge($payload, $user);
        }

        if ($this->isPayuProvider()) {
            $service = (string) ($payload['service'] ?? '');
            $paymentType = (string) ($payload['payment_type'] ?? 'prepaid');
            if ($service !== 'prepaid-postpaid' || $paymentType !== 'prepaid') {
                throw new \RuntimeException('Only prepaid mobile recharge is enabled in the current PayU live setup.');
            }

            return $this->submitPayuRecharge($payload, $user, $request);
        }

        throw new \RuntimeException('BBPS provider is permanently disabled. Only PayU prepaid recharge is supported.');
    }

    public function syncStatusByRequestId(string $requestId): array
    {
        if ($requestId === '') {
            throw new \RuntimeException('Missing provider request id for status sync.');
        }

        if ($this->isTestMode() && !$this->isPayuProvider()) {
            return [
                'responseCode' => '000',
                'responseReason' => 'SUCCESS',
                'txnList' => [[
                    'txnStatus' => 'SUCCESS',
                    'txnReferenceId' => 'MOCK' . now()->format('ymdHis'),
                    'approvalRefNumber' => 'MOCK' . random_int(100000, 999999),
                ]],
            ];
        }

        if ($this->isPayuProvider()) {
            return $this->syncPayuStatusByRequestId($requestId);
        }

        $response = $this->postEncryptedJson('/transactionstatus/fetchinfo/json', [
            'trackingType' => 'REQUEST_ID',
            'trackingValue' => $requestId,
        ]);

        return $response['response'] ?? [];
    }

    public function decodeIncomingPayload(string $rawBody = '', array $input = []): array
    {
        $encrypted = trim((string) ($input['encRequest'] ?? $input['enc_request'] ?? $input['encResponse'] ?? $input['enc_response'] ?? ''));
        if ($encrypted !== '') {
            return $this->decodeDecryptedPayload($this->decrypt($encrypted));
        }

        $body = trim($rawBody);
        if ($body === '') {
            return [];
        }

        $json = json_decode($body, true);
        if (is_array($json)) {
            $encrypted = trim((string) ($json['encRequest'] ?? $json['enc_request'] ?? $json['encResponse'] ?? $json['enc_response'] ?? ''));
            if ($encrypted !== '') {
                return $this->decodeDecryptedPayload($this->decrypt($encrypted));
            }

            return $json;
        }

        parse_str($body, $parsedForm);
        if (is_array($parsedForm) && $parsedForm !== []) {
            $encrypted = trim((string) ($parsedForm['encRequest'] ?? $parsedForm['enc_request'] ?? $parsedForm['encResponse'] ?? $parsedForm['enc_response'] ?? ''));
            if ($encrypted !== '') {
                return $this->decodeDecryptedPayload($this->decrypt($encrypted));
            }
        }

        if (Str::startsWith($body, '<')) {
            return $this->xmlToArray($body);
        }

        return $this->decodeDecryptedPayload($this->decrypt($body));
    }

    private function fetchBillerInfo(string $billerId): array
    {
        $cacheKey = 'billavenue_biller_' . md5($this->instituteId . '|' . $billerId);

        return Cache::remember($cacheKey, now()->addHours(23), function () use ($billerId) {
            $response = $this->postEncryptedJson('/extMdmCntrl/mdmRequestNew/json', [
                'billerId' => [$billerId],
            ]);

            $biller = Arr::first(Arr::get($response['response'], 'biller', []));
            if (!is_array($biller)) {
            throw new \RuntimeException('Recharge provider did not return biller information for ' . $billerId . '.');
            }

            return $biller;
        });
    }

    private function postEncryptedJson(string $path, array $payload, ?string $requestId = null): array
    {
        $requestId = $requestId ?: $this->generateRequestId();
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode recharge provider payload.');
        }

        $response = Http::asForm()
            ->timeout($this->timeoutSeconds)
            ->post($this->baseUrl . $path, [
                'accessCode' => $this->accessCode,
                'requestId' => $requestId,
                'encRequest' => $this->encrypt($encoded),
                'ver' => '1.0',
                'instituteId' => $this->instituteId,
            ]);

        $body = trim((string) $response->body());
        $decoded = $this->decodeResponse($body);

        if (!$response->successful()) {
            throw new \RuntimeException($this->extractErrorMessage($decoded) ?: 'Recharge provider request failed.');
        }

        if (($decoded['responseCode'] ?? null) && (string) $decoded['responseCode'] !== '000') {
            throw new \RuntimeException($this->extractErrorMessage($decoded) ?: ('Recharge provider request failed with code ' . $decoded['responseCode']));
        }

        return [
            'request_id' => $requestId,
            'response' => $decoded,
            'raw' => $body,
        ];
    }

    private function decodeResponse(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $json = json_decode($raw, true);
        if (is_array($json)) {
            if (isset($json['responseCode'])) {
                return $json;
            }

            foreach (['encResponse', 'encresponse', 'enc_response', 'response'] as $key) {
                $candidate = $json[$key] ?? null;
                if (is_string($candidate) && $candidate !== '') {
                    return $this->decodeDecryptedPayload($this->decrypt($candidate));
                }
            }

            return $json;
        }

        if (Str::startsWith($raw, '<')) {
            return $this->xmlToArray($raw);
        }

        return $this->decodeDecryptedPayload($this->decrypt($raw));
    }

    private function decodeDecryptedPayload(string $payload): array
    {
        $payload = trim($payload);
        if ($payload === '') {
            return [];
        }

        if (Str::startsWith($payload, '<')) {
            return $this->xmlToArray($payload);
        }

        $json = json_decode($payload, true);

        return is_array($json) ? $json : [];
    }

    private function encrypt(string $payload): string
    {
        $encrypted = openssl_encrypt($payload, 'AES-128-CBC', $this->keyBytes(), OPENSSL_RAW_DATA, $this->ivBytes());
        if ($encrypted === false) {
            throw new \RuntimeException('Unable to encrypt recharge provider request.');
        }

        return base64_encode($encrypted);
    }

    private function decrypt(string $payload): string
    {
        $decoded = base64_decode(trim($payload), true);
        if ($decoded === false) {
            return '';
        }

        $decrypted = openssl_decrypt($decoded, 'AES-128-CBC', $this->keyBytes(), OPENSSL_RAW_DATA, $this->ivBytes());

        return $decrypted === false ? '' : $decrypted;
    }

    private function keyBytes(): string
    {
        if (preg_match('/^[a-f0-9]{32}$/i', $this->workingKey) === 1) {
            $decoded = hex2bin($this->workingKey);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return substr(str_pad($this->workingKey, 16, '0'), 0, 16);
    }

    private function ivBytes(): string
    {
        if (preg_match('/^[a-f0-9]{32}$/i', $this->iv) === 1) {
            $decoded = hex2bin($this->iv);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return substr(str_pad($this->iv, 16, '0'), 0, 16);
    }

    private function xmlToArray(string $xml): array
    {
        try {
            $element = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($element === false) {
                return [];
            }

            $json = json_encode($element);

            return is_string($json) ? (json_decode($json, true) ?: []) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function buildAgentDeviceInfo(Request $request): array
    {
        return [
            'initChannel' => $this->channel !== '' ? $this->channel : 'INT',
            'ip' => $request->ip() ?: '127.0.0.1',
            'mac' => '00-00-00-00-00-00',
        ];
    }

    private function buildCustomerInfo(array $payload, User $user): array
    {
        $customerMobile = '';
        foreach ([
            $payload['customer_mobile'] ?? null,
            $payload['mobile'] ?? null,
            $user->phone ?? null,
        ] as $candidate) {
            $digits = preg_replace('/\D+/', '', (string) ($candidate ?? ''));
            if ($digits !== '') {
                $customerMobile = $digits;
                break;
            }
        }

        if (strlen($customerMobile) > 10) {
            $customerMobile = substr($customerMobile, -10);
        }

        if ($customerMobile === '' || preg_match('/^[6-9][0-9]{9}$/', $customerMobile) !== 1) {
            throw new \RuntimeException('Customer mobile number must be a valid 10-digit Indian mobile number.');
        }

        return array_filter([
            'customerMobile' => $customerMobile,
            'customerEmail' => trim((string) ($user->email ?? '')),
            'customerPan' => strtoupper(trim((string) ($user->pan_number ?? ''))),
        ], static fn ($value) => $value !== '');
    }

    private function buildInputParams(array $payload, array $biller): array
    {
        $params = $this->flattenInputParams(Arr::get($biller, 'billerInputParams', []));
        if ($params === []) {
            return [];
        }

        $values = $this->semanticInputValues($payload);
        $built = [];

        foreach ($params as $param) {
            $paramName = trim((string) ($param['paramName'] ?? ''));
            if ($paramName === '') {
                continue;
            }

            $value = $this->matchParamValue($paramName, $values);
            $optional = filter_var($param['isOptional'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (($value === null || $value === '') && !$optional) {
                throw new \RuntimeException('Missing required biller field: ' . $paramName);
            }

            if ($value !== null && $value !== '') {
                $built[] = [
                    'paramName' => $paramName,
                    'paramValue' => (string) $value,
                ];
            }
        }

        return $built;
    }

    private function flattenInputParams(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $params = [];
        foreach ($raw as $group) {
            if (isset($group['paramsList']) && is_array($group['paramsList'])) {
                foreach ($group['paramsList'] as $param) {
                    if (is_array($param)) {
                        $params[] = $param;
                    }
                }
                continue;
            }

            if (isset($group['paramInfo']) && is_array($group['paramInfo'])) {
                foreach ($group['paramInfo'] as $param) {
                    if (is_array($param)) {
                        $params[] = $param;
                    }
                }
                continue;
            }

            if (is_array($group) && isset($group['paramName'])) {
                $params[] = $group;
            }
        }

        return $params;
    }

    private function semanticInputValues(array $payload): array
    {
        return [
            'mobile' => (string) ($payload['mobile'] ?? ''),
            'customer_mobile' => (string) ($payload['customer_mobile'] ?? ''),
            'circle' => (string) ($payload['circle'] ?? ''),
            'circle_ref_id' => (string) ($payload['circle_ref_id'] ?? $this->resolvePayuCircleRefId((string) ($payload['circle'] ?? ''))),
            'operator_code' => (string) ($payload['operator_code'] ?? ''),
            'location' => (string) ($payload['circle'] ?? ''),
            'state' => (string) ($payload['state'] ?? ''),
            'city' => (string) ($payload['city'] ?? ''),
            'subscriber_id' => (string) ($payload['subscriber_id'] ?? ''),
            'service_number' => (string) ($payload['service_number'] ?? ''),
            'service_id' => (string) ($payload['service_number'] ?? ''),
            'consumer_number' => (string) ($payload['service_number'] ?? ''),
            'customer_id' => (string) ($payload['customer_id'] ?? ''),
            'account_id' => (string) ($payload['account_id'] ?? ''),
            'account_number' => (string) ($payload['account_number'] ?? ''),
            'card_number' => (string) ($payload['card_number'] ?? ''),
            'student_id' => (string) ($payload['student_id'] ?? ''),
            'policy_number' => (string) ($payload['policy_number'] ?? ''),
            'loan_account_number' => (string) ($payload['loan_account_number'] ?? ''),
            'amount' => (string) ($payload['amount'] ?? ''),
        ];
    }

    private function matchParamValue(string $paramName, array $values): ?string
    {
        $name = strtolower((string) preg_replace('/[^a-z0-9]+/i', ' ', $paramName));
        $checks = [
            [['mobile number', 'mobilenumber', 'registered mobile', 'customer mobile', 'mobile no', 'mobile'], ['mobile', 'customer_mobile']],
            [['circle refid', 'circle ref id', 'circlerefid'], ['circle_ref_id']],
            [['operator code', 'operatorcode'], ['operator_code']],
            [['location', 'circle', 'state', 'region'], ['circle', 'location', 'state', 'city']],
            [['subscriber', 'smart card', 'vc number'], ['subscriber_id']],
            [['service number', 'service no', 'consumer number', 'consumer no', 'service id', 'ca number'], ['service_number', 'service_id', 'consumer_number', 'customer_id', 'account_id']],
            [['customer id', 'consumer id'], ['customer_id', 'account_id']],
            [['account id', 'broadband account', 'account number'], ['account_id', 'account_number']],
            [['card number', 'metro card'], ['card_number']],
            [['student id', 'roll number'], ['student_id']],
            [['policy number', 'policy no'], ['policy_number']],
            [['loan account', 'loan number', 'emi number'], ['loan_account_number', 'account_number']],
            [['amount', 'recharge amount', 'bill amount', 'contribution amount'], ['amount']],
        ];

        foreach ($checks as [$phrases, $keys]) {
            foreach ($phrases as $phrase) {
                if (!str_contains($name, $phrase)) {
                    continue;
                }

                foreach ($keys as $key) {
                    $value = trim((string) ($values[$key] ?? ''));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    private function resolvePayuCircleRefId(string $circle): string
    {
        $normalized = strtolower(trim($circle));

        return match ($normalized) {
            'andhra pradesh', 'telangana', 'andhra pradesh & telangana', 'andhra pradesh and telangana' => '2',
            default => '',
        };
    }

    private function resolveBillerId(array $payload): string
    {
        $service = (string) ($payload['service'] ?? '');
        $provider = (string) ($payload['provider'] ?? '');
        $paymentType = (string) ($payload['payment_type'] ?? '');
        $adminSettingKey = $this->resolveRechargeBillerAdminSettingKey($service, $provider, $paymentType);
        if ($adminSettingKey !== '') {
            $adminMapped = trim((string) AdminSetting::getValue($adminSettingKey, ''));
            if ($adminMapped !== '') {
                return $adminMapped;
            }
        }
        $map = config('recharge_billers', []);

        if ($service === 'prepaid-postpaid') {
            return trim((string) Arr::get($map, $service . '.' . $paymentType . '.' . $provider, ''));
        }

        return trim((string) Arr::get($map, $service . '.' . $provider, ''));
    }

    private function resolveRechargeBillerAdminSettingKey(string $service, string $provider, string $paymentType): string
    {
        if ($service !== 'prepaid-postpaid' || $paymentType !== 'prepaid') {
            return '';
        }

        return match (strtolower(trim($provider))) {
            'airtel' => 'sys_gateway_recharge_biller_mobile_prepaid_airtel',
            'bsnl' => 'sys_gateway_recharge_biller_mobile_prepaid_bsnl',
            'jio' => 'sys_gateway_recharge_biller_mobile_prepaid_jio',
            'mtnl' => 'sys_gateway_recharge_biller_mobile_prepaid_mtnl',
            'vi' => 'sys_gateway_recharge_biller_mobile_prepaid_vi',
            default => '',
        };
    }

    private function supportsFetch(array $biller): bool
    {
        return strtoupper((string) Arr::get($biller, 'billerFetchRequiremet', 'NOT_SUPPORTED')) !== 'NOT_SUPPORTED';
    }

    private function supportsValidation(array $biller): bool
    {
        return strtoupper((string) Arr::get($biller, 'billerSupportBillValidation', 'NOT_SUPPORTED')) !== 'NOT_SUPPORTED';
    }

    private function validationRequiresAmount(array $biller): bool
    {
        return strtoupper((string) Arr::get($biller, 'rechargeAmountInValidationRequest', 'NOT_SUPPORTED')) === 'SUPPORTED';
    }

    private function calculateCcf1(int $amountPaise, mixed $ccf1Config): int
    {
        if (!is_array($ccf1Config) || strtoupper((string) ($ccf1Config['feeCode'] ?? '')) !== 'CCF1') {
            return 0;
        }

        $flatFee = (float) ($ccf1Config['flatFee'] ?? 0);
        $percentFee = (float) ($ccf1Config['percentFee'] ?? 0);
        $baseFee = ($amountPaise * $percentFee / 100) + $flatFee;
        $gst = $baseFee * 0.18;

        return (int) floor($baseFee + $gst);
    }

    private function normalizeBooleanString(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }

    public function normalizeGatewayStatus(array $response): string
    {
        $payuTxnStatus = strtoupper((string) ($response['txnStatus'] ?? ''));
        $payuMessage = strtoupper((string) ($response['message'] ?? ''));
        if (in_array($payuTxnStatus, ['PAYMENT_SUCCESS', 'SUCCESS'], true)) {
            return 'completed';
        }
        if (in_array($payuTxnStatus, ['PAYMENT_FAILURE', 'FAILURE'], true)) {
            return 'failed';
        }
        if (in_array($payuTxnStatus, ['PAYMENT_PENDING', 'RECORD_NOT_FOUND'], true)) {
            return 'pending';
        }
        if ($payuMessage === 'PAYMENT_REQUEST_PENDING') {
            return 'pending';
        }
        if ($payuMessage === 'PAYMENT_REQUEST_FAILED') {
            return 'failed';
        }

        $txnStatus = strtoupper((string) ($response['txnStatus'] ?? ''));
        $responseCode = (string) ($response['responseCode'] ?? '');
        $txnRespType = strtoupper((string) ($response['txnRespType'] ?? ''));
        $responseReason = strtoupper((string) ($response['responseReason'] ?? ''));

        if (in_array($txnStatus, ['SUCCESS', 'COMPLETED'], true)) {
            return 'completed';
        }
        if (in_array($txnStatus, ['FAILURE', 'FAILED', 'REVERSED', 'REFUND', 'REFUNDED'], true)) {
            return 'failed';
        }
        if ($txnStatus !== '' && (str_contains($txnStatus, 'PENDING') || str_contains($txnStatus, 'AWAIT') || str_contains($txnStatus, 'PROCESS'))) {
            return 'pending';
        }
        if ($responseCode === '300' || str_contains($responseReason, 'REFUND')) {
            return 'failed';
        }
        if (str_contains($txnRespType, 'REVERSAL') || str_contains($responseReason, 'FAIL')) {
            return 'failed';
        }
        if (str_contains($txnRespType, 'AWAIT') || str_contains($responseReason, 'PENDING')) {
            return 'pending';
        }
        if ($responseCode !== '' && $responseCode !== '000') {
            return 'failed';
        }

        return 'completed';
    }

    private function assertBillerSupportsTransaction(array $biller, int $amountPaise): void
    {
        $walletMode = $this->resolveConfiguredOption($biller['billerPaymentModes'] ?? [], ['paymentMode', 'paymentModeName'], 'Wallet payment mode');
        if ($walletMode !== null) {
            $this->assertAmountWithinRange($walletMode, $amountPaise, 'Wallet payment mode');
        }

        $channel = $this->resolveConfiguredOption($biller['billerPaymentChannels'] ?? [], ['paymentChannelName'], $this->channel . ' payment channel');
        if ($channel !== null) {
            $this->assertAmountWithinRange($channel, $amountPaise, $this->channel . ' payment channel');
        }
    }

    private function resolveConfiguredOption(mixed $rawOptions, array $nameKeys, string $errorLabel): ?array
    {
        $options = $this->flattenConfigItems($rawOptions);
        if ($options === []) {
            return null;
        }

        foreach ($options as $option) {
            foreach ($nameKeys as $nameKey) {
                $name = strtoupper(trim((string) ($option[$nameKey] ?? '')));
                if ($name !== '' && ($name === strtoupper($this->channel) || $name === 'WALLET')) {
                    return $option;
                }
            }
        }

        throw new \RuntimeException('Selected biller does not support the required ' . $errorLabel . '.');
    }

    private function flattenConfigItems(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            foreach (['paymentModeList', 'paymentChannelList', 'paramsList', 'paramInfo'] as $listKey) {
                if (isset($entry[$listKey]) && is_array($entry[$listKey])) {
                    foreach ($entry[$listKey] as $child) {
                        if (is_array($child)) {
                            $items[] = $child;
                        }
                    }
                    continue 2;
                }
            }

            $items[] = $entry;
        }

        return $items;
    }

    private function assertAmountWithinRange(array $option, int $amountPaise, string $label): void
    {
        $minAmount = (int) ($option['minAmount'] ?? 0);
        $maxAmount = (int) ($option['maxAmount'] ?? 0);

        if ($minAmount > 0 && $amountPaise < $minAmount) {
            throw new \RuntimeException($label . ' minimum amount is Rs. ' . number_format($minAmount / 100, 2, '.', '') . '.');
        }

        if ($maxAmount > 0 && $amountPaise > $maxAmount) {
            throw new \RuntimeException($label . ' maximum amount is Rs. ' . number_format($maxAmount / 100, 2, '.', '') . '.');
        }
    }

    private function extractErrorMessage(array $response): string
    {
        $errorInfo = $response['errorInfo'] ?? [];
        if (is_array($errorInfo)) {
            $error = $errorInfo['error'] ?? Arr::first($errorInfo);
            if (is_array($error)) {
                return (string) ($error['errorMessage'] ?? $error['ErrorMessage'] ?? '');
            }
        }

        return (string) ($response['responseReason'] ?? '');
    }

    private function generateRequestId(): string
    {
        $yearLastDigit = substr(now()->format('y'), -1);
        $dayOfYear = str_pad((string) now()->dayOfYear, 3, '0', STR_PAD_LEFT);
        $timePart = now()->format('Hi');
        $suffix = $yearLastDigit . $dayOfYear . $timePart;
        $prefix = strtoupper(Str::random(27));

        return substr($prefix . $suffix, 0, 35);
    }

    private function toPaise(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function isPayuProvider(): bool
    {
        return $this->isPayuProviderName($this->provider) || str_contains(strtolower($this->baseUrl), 'payu');
    }

    private function isPayuProviderName(string $provider): bool
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($provider)) === 'payu';
    }

    private function defaultPayuApiBaseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://bbps-sb.payu.in'
            : 'https://bbps.payu.in';
    }

    private function defaultPayuAuthBaseUrl(?string $baseUrl = null): string
    {
        $resolvedBaseUrl = strtolower(trim((string) ($baseUrl ?? $this->baseUrl ?? '')));
        if ($resolvedBaseUrl !== '') {
            // Keep auth host aligned with the configured NBC host to avoid invalid_client
            // due to sandbox credentials being sent to production OAuth endpoint.
            if (str_contains($resolvedBaseUrl, 'bbps-sb.payu.in')) {
                return 'https://uat-accounts.payu.in';
            }
            if (str_contains($resolvedBaseUrl, 'bbps.payu.in')) {
                return 'https://accounts.payu.in';
            }
        }

        return $this->isTestMode()
            ? 'https://uat-accounts.payu.in'
            : 'https://accounts.payu.in';
    }

    private function submitPayuRecharge(array $payload, User $user, Request $request): array
    {
        if (($payload['service'] ?? '') !== 'prepaid-postpaid' || ($payload['payment_type'] ?? 'prepaid') !== 'prepaid') {
            throw new \RuntimeException('The attached PayU recharge API document currently supports prepaid mobile recharge in this integration. Other recharge services can remain on the existing provider.');
        }

        if (!$this->isConfigured()) {
            throw new \RuntimeException('PayU recharge credentials are incomplete. Please configure client id, client secret, agent id, and NBC base URL.');
        }

        $billerId = '';
        if (($payload['service'] ?? '') === 'prepaid-postpaid' && ($payload['payment_type'] ?? 'prepaid') === 'prepaid') {
            // PayU prepaid should work without manually maintaining biller ids in admin settings.
            $billerId = $this->resolvePayuPrepaidBillerIdByProvider((string) ($payload['provider'] ?? ''));
        } else {
            $billerId = $this->resolveBillerId($payload);
        }
        if ($billerId === '') {
            throw new \RuntimeException('Unable to resolve prepaid biller from PayU for the selected operator. Please ask PayU to enable prepaid billers on this recharge client.');
        }

        $biller = $this->fetchPayuBillerInfo($billerId, $payload);
        $customerInfo = $this->buildCustomerInfo($payload, $user);
        $customerParams = $this->buildPayuCustomerParams($payload, $biller);
        $this->assertPayuBillerSupportsTransaction($biller, (float) $payload['amount']);

        $validationResponse = null;
        if ($this->payuSupportsValidation($biller)) {
            try {
                $validationResponse = $this->postPayuJson('/payu-nbc/v2/nbc/paymentValidation', [
                    'agentId' => $this->agentId,
                    'billerId' => $billerId,
                    'customerParams' => $customerParams,
                    'deviceDetails' => [
                        'INITIATING_CHANNEL' => $this->channel !== '' ? $this->channel : 'INT',
                        'IP' => $request->ip() ?: '127.0.0.1',
                        'MAC' => '00-00-00-00-00-00',
                    ],
                    'customerName' => trim((string) $user->name),
                    'customerPhoneNumber' => $customerInfo['customerMobile'],
                    'paidAmount' => round((float) $payload['amount'], 2),
                    'refId' => $payload['reference'],
                    'timeStamp' => $this->payuTimestamp(),
                ], 'create_transactions');
            } catch (\Throwable $e) {
                if (!$this->canProceedWithoutPayuValidation($e)) {
                    throw $e;
                }

                $validationResponse = [
                    'status' => 'SKIPPED',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $paymentResponse = $this->postPayuJson('/payu-nbc/v1/nbc/billpaymentrequest', [
            'agentId' => $this->agentId,
            'billerId' => $billerId,
            'customerParams' => $customerParams,
            'deviceDetails' => [
                'INITIATING_CHANNEL' => $this->channel !== '' ? $this->channel : 'INT',
                'IP' => $request->ip() ?: '127.0.0.1',
                'MAC' => '00-00-00-00-00-00',
            ],
            'paidAmount' => round((float) $payload['amount'], 2),
            'paymentName' => 'TOTAL',
            'paymentDetails' => [
                'paymentMode' => 'WALLET',
                'params' => [
                    'walletName' => 'XENN TECH Wallet',
                ],
            ],
            'userDetails' => [
                'firstName' => Str::limit(trim((string) $user->name), 100, ''),
                'phone' => $customerInfo['customerMobile'],
                'email' => trim((string) ($user->email ?? '')),
            ],
            'COUcustConvFee' => 0,
            'isQuickPay' => !$this->payuRequiresFetch($biller),
            'timeStamp' => $this->payuTimestamp(),
            'refId' => $payload['reference'],
            'additionalParams' => [
                'agentTxnID' => $payload['reference'],
            ],
        ], 'create_transactions', 'POST', true);

        $paymentPayload = $paymentResponse['payload'] ?? [];
        $additionalParams = is_array($paymentPayload['additionalParams'] ?? null) ? $paymentPayload['additionalParams'] : [];
        $status = $this->normalizeGatewayStatus(array_merge($paymentPayload, [
            'message' => $paymentPayload['message'] ?? null,
        ]));

        return [
            'mode' => 'payu_nbc',
            'biller_id' => $billerId,
            'biller' => $biller,
            'validation' => $validationResponse['payload'] ?? null,
            'fetch' => null,
            'payment' => [
                'status' => $paymentResponse['status'] ?? null,
                'message' => $paymentPayload['message'] ?? null,
                'refId' => $paymentPayload['refId'] ?? $payload['reference'],
                'billerId' => $paymentPayload['billerId'] ?? $billerId,
                'txnRefId' => $additionalParams['txnReferenceId'] ?? null,
                'additionalParams' => $additionalParams,
            ],
            'request_id' => (string) ($paymentPayload['refId'] ?? $payload['reference']),
            'status' => $status,
            'txn_ref_id' => $additionalParams['txnReferenceId'] ?? null,
            'approval_ref_number' => $additionalParams['txnReferenceId'] ?? null,
            'ccf1' => 0,
            'raw' => [
                'validation' => $validationResponse,
                'payment' => $paymentResponse,
            ],
        ];
    }

    private function buildManualPayuFallbackResult(array $payload, \Throwable $e): array
    {
        $reference = (string) ($payload['reference'] ?? ('PAYUMANUAL' . now()->format('ymdHis')));
        $fallbackMessage = 'PAYMENT_REQUEST_PENDING';

        return [
            'mode' => 'payu_manual',
            'biller_id' => $this->resolveBillerId($payload),
            'biller' => [
                'billerName' => strtoupper((string) ($payload['provider'] ?? 'PAYU MANUAL')),
                'billerCategory' => (string) ($payload['service'] ?? 'Recharge'),
            ],
            'validation' => null,
            'fetch' => null,
            'payment' => [
                'status' => 'SUCCESS',
                'message' => $fallbackMessage,
                'refId' => $reference,
                'billerId' => $this->resolveBillerId($payload),
                'txnStatus' => 'PAYMENT_PENDING',
                'additionalParams' => [
                    'manualFallback' => true,
                    'fallbackReason' => $e->getMessage(),
                ],
            ],
            'request_id' => $reference,
            'status' => 'pending',
            'txn_ref_id' => null,
            'approval_ref_number' => null,
            'ccf1' => 0,
            'raw' => [
                'manual_fallback' => true,
                'fallback_reason' => $e->getMessage(),
            ],
        ];
    }

    private function syncPayuStatusByRequestId(string $requestId): array
    {
        $response = $this->postPayuJson('/payu-nbc/v2/nbc/status/billpayment?refId=' . urlencode($requestId), null, 'read_transactions', 'GET');

        return $response['payload'] ?? [];
    }

    private function postPayuJson(string $path, ?array $payload, string $scope, string $method = 'POST', bool $allowPending = false): array
    {
        $token = $this->getPayuAccessToken($scope);
        $isAbsolutePath = str_starts_with($path, 'http');
        $candidateBaseUrls = $isAbsolutePath ? [''] : $this->candidatePayuApiBases();
        $response = null;
        $lastThrowable = null;

        foreach ($candidateBaseUrls as $baseUrl) {
            $url = $isAbsolutePath ? $path : ($baseUrl . $path);
            try {
                $request = Http::timeout($this->timeoutSeconds)
                    ->withToken($token)
                    ->acceptJson()
                    ->contentType('application/json');

                $response = strtoupper($method) === 'GET'
                    ? $request->get($url)
                    : $request->post($url, $payload ?? []);

                break;
            } catch (\Throwable $e) {
                $lastThrowable = $e;
                if (!$this->isPayuDnsOrConnectionIssue($e) || $isAbsolutePath) {
                    throw $e;
                }
            }
        }

        if (!$response) {
            if ($lastThrowable) {
                throw $lastThrowable;
            }
            throw new \RuntimeException('Unable to connect to PayU API host.');
        }

        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new \RuntimeException('PayU returned an invalid response.');
        }

        if (!$response->successful()) {
            throw new \RuntimeException($this->extractPayuErrorMessage($decoded) ?: 'PayU request failed.');
        }

        if ($allowPending && $this->isPayuPendingResponse($decoded)) {
            return $decoded;
        }

        if ((int) ($decoded['code'] ?? 0) !== 200 || strtoupper((string) ($decoded['status'] ?? '')) !== 'SUCCESS') {
            throw new \RuntimeException($this->extractPayuErrorMessage($decoded) ?: 'PayU request failed.');
        }

        return $decoded;
    }

    private function getPayuAccessToken(string $scope): string
    {
        $cacheKey = 'payu_recharge_token_' . md5($this->accessCode . '|' . $scope . '|' . $this->authBaseUrl);

        return Cache::remember($cacheKey, now()->addMinutes(45), function () use ($scope) {
            $authBases = $this->candidatePayuAuthBases();
            if ($authBases === []) {
                throw new \RuntimeException('PayU auth base URL is missing.');
            }

            $credentialPairs = $this->candidatePayuCredentialPairs();
            if ($credentialPairs === []) {
                throw new \RuntimeException('PayU recharge OAuth credentials are missing.');
            }

            $lastDecoded = [];
            $lastThrowable = null;
            foreach ($authBases as $authBaseUrl) {
                foreach ($credentialPairs as $pair) {
                    try {
                        $response = Http::asForm()
                            ->timeout($this->timeoutSeconds)
                            ->post($authBaseUrl . '/oauth/token', [
                                'client_id' => $pair['client_id'],
                                'client_secret' => $pair['client_secret'],
                                'grant_type' => $this->grantType !== '' ? $this->grantType : 'client_credentials',
                                'scope' => $this->resolvePayuScope($scope),
                            ]);

                        $decoded = $response->json();
                        $lastDecoded = is_array($decoded) ? $decoded : [];
                        if ($response->successful() && is_array($decoded) && trim((string) ($decoded['access_token'] ?? '')) !== '') {
                            return (string) $decoded['access_token'];
                        }
                    } catch (\Throwable $e) {
                        $lastThrowable = $e;
                        if (!$this->isPayuDnsOrConnectionIssue($e)) {
                            throw $e;
                        }
                    }
                }
            }

            if ($lastThrowable && $lastDecoded === []) {
                throw $lastThrowable;
            }

            $message = $this->extractPayuAuthErrorMessage($lastDecoded);
            throw new \RuntimeException($message !== '' ? $message : 'Unable to generate PayU access token.');
        });
    }

    private function packedSecretForPayu(): string
    {
        return $this->secretKey;
    }

    private function candidatePayuAuthBases(): array
    {
        $bases = [];
        foreach ([
            $this->authBaseUrl,
            $this->defaultPayuAuthBaseUrl($this->baseUrl),
            'https://accounts.payu.in',
            'https://uat-accounts.payu.in',
        ] as $candidate) {
            $normalized = rtrim(trim((string) $candidate), '/');
            if ($normalized !== '' && !in_array($normalized, $bases, true)) {
                $bases[] = $normalized;
            }
        }

        return $bases;
    }

    private function candidatePayuApiBases(): array
    {
        $bases = [];
        foreach ([
            $this->baseUrl,
            $this->defaultPayuApiBaseUrl(),
            'https://bbps.payu.in',
            'https://bbps-sb.payu.in',
        ] as $candidate) {
            $normalized = rtrim(trim((string) $candidate), '/');
            if ($normalized !== '' && !in_array($normalized, $bases, true)) {
                $bases[] = $normalized;
            }
        }

        return $bases;
    }

    private function isPayuDnsOrConnectionIssue(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'could not resolve host')
            || str_contains($message, 'name or service not known')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'timed out');
    }

    private function candidatePayuCredentialPairs(): array
    {
        $pairs = [];
        $rechargeId = trim((string) $this->accessCode);
        $rechargeSecret = trim((string) $this->packedSecretForPayu());
        if ($rechargeId !== '' && $rechargeSecret !== '') {
            $pairs[] = ['client_id' => $rechargeId, 'client_secret' => $rechargeSecret];
        }

        $payuKey = trim((string) AdminSetting::getValue('sys_gateway_payu_key', env('PAYU_MERCHANT_KEY', '')));
        $payuSalt = trim((string) AdminSetting::getValue('sys_gateway_payu_salt', env('PAYU_MERCHANT_SALT', '')));
        if ($payuKey !== '' && $payuSalt !== '') {
            $duplicate = false;
            foreach ($pairs as $pair) {
                if ($pair['client_id'] === $payuKey && $pair['client_secret'] === $payuSalt) {
                    $duplicate = true;
                    break;
                }
            }
            if (!$duplicate) {
                $pairs[] = ['client_id' => $payuKey, 'client_secret' => $payuSalt];
            }
        }

        return $pairs;
    }

    private function resolvePayuScope(string $scope): string
    {
        if ($this->scope !== '') {
            return $this->scope;
        }

        return match ($scope) {
            'read_transactions' => 'read_transactions',
            'read_billers' => 'read_billers',
            default => 'create_transactions',
        };
    }

    private function fetchPayuBillerInfo(string $billerId, array $payload): array
    {
        $cacheKey = 'payu_biller_' . md5($billerId . '|' . ($payload['service'] ?? '') . '|' . ($payload['payment_type'] ?? ''));

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($billerId, $payload) {
            $category = $this->resolvePayuCategoryName($payload);
            $response = $this->postPayuJson(
                '/payu-nbc/v1/nbc/getBillerByBillerCategory?billerCategoryName=' . urlencode($category),
                null,
                'read_billers',
                'GET'
            );

            $billers = $response['payload']['billers'] ?? [];
            foreach ($billers as $biller) {
                if ((string) ($biller['billerId'] ?? '') === $billerId) {
                    return $biller;
                }
            }

            throw new \RuntimeException('PayU did not return biller information for ' . $billerId . '.');
        });
    }

    private function resolvePayuCategoryName(array $payload): string
    {
        return match ((string) ($payload['service'] ?? '')) {
            'prepaid-postpaid' => (($payload['payment_type'] ?? 'prepaid') === 'prepaid' ? 'MOBILE PREPAID' : 'MOBILE POSTPAID'),
            'electricity' => 'ELECTRICITY',
            'dth' => 'DTH',
            default => strtoupper(str_replace('-', ' ', (string) ($payload['service'] ?? 'RECHARGE'))),
        };
    }

    private function resolvePayuPrepaidBillerIdByProvider(string $provider): string
    {
        $normalizedProvider = strtolower(trim($provider));
        if ($normalizedProvider === '') {
            return '';
        }

        $cacheKey = 'payu_prepaid_biller_by_provider_' . md5($normalizedProvider . '|' . $this->baseUrl . '|' . $this->agentId);

        return (string) Cache::remember($cacheKey, now()->addHours(12), function () use ($normalizedProvider) {
            $response = $this->postPayuJson(
                '/payu-nbc/v1/nbc/getBillerByBillerCategory?billerCategoryName=' . urlencode('MOBILE PREPAID'),
                null,
                'read_billers',
                'GET'
            );

            $billers = is_array($response['payload']['billers'] ?? null) ? $response['payload']['billers'] : [];
            if ($billers === []) {
                return '';
            }

            $providerAliases = [
                'airtel' => ['airtel', 'bharti airtel'],
                'bsnl' => ['bsnl', 'bharat sanchar'],
                'jio' => ['jio', 'reliance jio'],
                'mtnl' => ['mtnl', 'mahanagar'],
                'vi' => ['vi', 'vodafone', 'idea', 'voda idea'],
            ];

            $targets = $providerAliases[$normalizedProvider] ?? [$normalizedProvider];
            foreach ($billers as $biller) {
                $billerName = strtolower(trim((string) ($biller['billerName'] ?? '')));
                $billerId = trim((string) ($biller['billerId'] ?? ''));
                if ($billerId === '' || $billerName === '') {
                    continue;
                }

                foreach ($targets as $needle) {
                    if ($needle !== '' && str_contains($billerName, $needle)) {
                        return $billerId;
                    }
                }
            }

            return '';
        });
    }

    private function buildPayuCustomerParams(array $payload, array $biller): array
    {
        $params = is_array($biller['customerParams'] ?? null) ? $biller['customerParams'] : [];
        if ($params === []) {
            return [];
        }

        $values = $this->semanticInputValues($payload);
        $built = [];

        foreach ($params as $param) {
            $paramName = trim((string) ($param['paramName'] ?? ''));
            if ($paramName === '') {
                continue;
            }

            $value = $this->matchParamValue($paramName, $values);
            if ($value !== null && $value !== '') {
                $built[$paramName] = (string) $value;
            }
        }

        foreach ($params as $param) {
            $paramName = trim((string) ($param['paramName'] ?? ''));
            if ($paramName === '') {
                continue;
            }

            if (!array_key_exists($paramName, $built) && strcasecmp($paramName, 'OperatorCode') === 0) {
                $built[$paramName] = $this->resolvePayuOperatorCode($payload, $param);
            }

            if (!array_key_exists($paramName, $built) && strcasecmp($paramName, 'CircleRefID') === 0) {
                $built[$paramName] = $this->resolvePayuCircleRefId((string) ($payload['circle'] ?? ''));
            }

            $optional = filter_var($param['optional'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (($built[$paramName] ?? '') === '' && !$optional) {
                throw new \RuntimeException('Missing required biller field: ' . $paramName);
            }
        }

        if (($payload['provider'] ?? '') === 'BSNL' || ($payload['provider'] ?? '') === 'MTNL') {
            $rechargeType = trim((string) ($payload['recharge_type'] ?? ''));
            if ($rechargeType !== '') {
                $built['RechargeType'] = strtoupper($rechargeType);
            }
        }

        return $built;
    }

    private function resolvePayuOperatorCode(array $payload, array $param): string
    {
        $fromPayload = trim((string) ($payload['operator_code'] ?? ''));
        if ($fromPayload !== '') {
            return $fromPayload;
        }

        $regex = trim((string) ($param['regex'] ?? ''));
        if ($regex !== '' && preg_match('/^\^?([A-Z0-9]+)\$?$/i', $regex, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return '';
    }

    private function payuSupportsValidation(array $biller): bool
    {
        return strtoupper((string) ($biller['supportBillValidation'] ?? 'NOT_SUPPORTED')) !== 'NOT_SUPPORTED';
    }

    private function payuRequiresFetch(array $biller): bool
    {
        return strtoupper((string) ($biller['fetchOption'] ?? 'NOT_SUPPORTED')) === 'MANDATORY'
            && !filter_var($biller['isAdhoc'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function assertPayuBillerSupportsTransaction(array $biller, float $amount): void
    {
        $channel = $this->resolvePayuPaymentChannel($biller['paymentChannelsAllowed'] ?? [], $this->channel);
        if ($channel !== null) {
            $this->assertPayuAmountWithinRange($channel, $amount, $this->channel . ' payment channel');
        }

        $walletMode = $this->resolvePayuPaymentMode($biller['paymentModesAllowed'] ?? [], 'Wallet');
        if ($walletMode !== null) {
            $this->assertPayuAmountWithinRange($walletMode, $amount, 'Wallet payment mode');
        }
    }

    private function resolvePayuPaymentChannel(mixed $channels, string $channel): ?array
    {
        if (!is_array($channels)) {
            return null;
        }

        foreach ($channels as $entry) {
            if (strtoupper(trim((string) ($entry['paymentMode'] ?? ''))) === strtoupper($channel)) {
                return $entry;
            }
        }

        return null;
    }

    private function resolvePayuPaymentMode(mixed $modes, string $mode): ?array
    {
        if (!is_array($modes)) {
            return null;
        }

        foreach ($modes as $entry) {
            if (strtoupper(trim((string) ($entry['paymentMode'] ?? ''))) === strtoupper($mode)) {
                return $entry;
            }
        }

        return null;
    }

    private function assertPayuAmountWithinRange(array $option, float $amount, string $label): void
    {
        $minAmount = (float) ($option['minLimit'] ?? 0);
        $maxAmount = (float) ($option['maxLimit'] ?? 0);

        if ($minAmount > 0 && $amount < $minAmount) {
            throw new \RuntimeException($label . ' minimum amount is Rs. ' . number_format($minAmount, 2, '.', '') . '.');
        }

        if ($maxAmount > 0 && $amount > $maxAmount) {
            throw new \RuntimeException($label . ' maximum amount is Rs. ' . number_format($maxAmount, 2, '.', '') . '.');
        }
    }

    private function payuTimestamp(): string
    {
        return now('Asia/Kolkata')->format('Y-m-d H:i:s');
    }

    private function canProceedWithoutPayuValidation(\Throwable $e): bool
    {
        $message = strtolower(trim($e->getMessage()));

        return str_contains($message, 'could not validate this time')
            || str_contains($message, 'please try again later');
    }

    private function extractPayuErrorMessage(array $response): string
    {
        $payload = $response['payload'] ?? [];
        if (is_array($payload) && isset($payload['errors']) && is_array($payload['errors'])) {
            $first = Arr::first($payload['errors']);
            if (is_array($first)) {
                return (string) ($first['reason'] ?? $first['errorCode'] ?? '');
            }
        }

        if (is_array($payload)) {
            return (string) ($payload['message'] ?? '');
        }

        return '';
    }

    private function extractPayuAuthErrorMessage(array $response): string
    {
        $error = trim((string) ($response['error'] ?? ''));
        $description = trim((string) ($response['error_description'] ?? ''));

        if ($error === 'invalid_client') {
            return 'PayU recharge client authentication failed. Verify that Admin Settings contains the PayU recharge OAuth client id and client secret, not regular payment gateway credentials.';
        }

        if ($error === 'invalid_scope') {
            return 'PayU recharge scope is not enabled for this client. Ask PayU to whitelist the required recharge scopes for your OAuth client.';
        }

        if ($description !== '') {
            return $description;
        }

        return $error;
    }

    private function isPayuPendingResponse(array $response): bool
    {
        $payload = $response['payload'] ?? [];
        $message = strtoupper((string) (is_array($payload) ? ($payload['message'] ?? '') : ''));

        return $message === 'PAYMENT_REQUEST_PENDING';
    }

    private function mockRecharge(array $payload, User $user): array
    {
        $reference = 'BBPSTEST' . now()->format('ymdHis') . random_int(1000, 9999);
        $approval = 'APR' . random_int(10000000, 99999999);

        return [
            'mode' => 'billavenue_bbps',
            'biller_id' => $this->resolveBillerId($payload) ?: 'BILAVAIRTEL001',
            'biller' => [
                'billerName' => strtoupper((string) ($payload['provider'] ?? 'Demo Biller')),
                'billerCategory' => (string) ($payload['service'] ?? 'Recharge'),
            ],
            'payment' => [
                'responseCode' => '000',
                'responseReason' => 'Successful',
                'txnRefId' => $reference,
                'approvalRefNumber' => $approval,
                'txnRespType' => 'FORWARD TYPE RESPONSE',
                'respAmount' => (string) $this->toPaise((float) $payload['amount']),
                'respCustomerName' => $user->name,
            ],
            'request_id' => $this->generateRequestId(),
            'status' => 'completed',
            'txn_ref_id' => $reference,
            'approval_ref_number' => $approval,
            'ccf1' => 0,
            'validation' => null,
            'fetch' => null,
            'raw' => ['payment' => ['mock' => true]],
        ];
    }
}
