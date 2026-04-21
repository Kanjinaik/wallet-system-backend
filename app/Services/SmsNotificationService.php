<?php

namespace App\Services;

use App\Models\AdminSetting;
use Illuminate\Support\Facades\Http;

class SmsNotificationService
{
    public function sendRechargeStatus(string $phone, string $status, string $provider, float $amount, string $reference): void
    {
        if (!$this->notificationsEnabled()) {
            return;
        }

        $providerKey = $this->provider();
        if (!in_array($providerKey, ['msg91', 'fast2sms'], true)) {
            return;
        }

        $normalizedPhone = $this->normalizeIndianPhone($phone);
        if ($normalizedPhone === '') {
            return;
        }

        $message = $this->buildRechargeMessage($status, $provider, $amount, $reference);
        if ($message === '') {
            return;
        }

        try {
            if ($providerKey === 'msg91') {
                $this->sendViaMsg91($normalizedPhone, $message);
                return;
            }

            $this->sendViaFast2Sms($normalizedPhone, $message);
        } catch (\Throwable) {
            // SMS delivery should never block or fail the recharge flow.
        }
    }

    private function notificationsEnabled(): bool
    {
        return AdminSetting::getValue('sys_sms_notifications', '0') === '1';
    }

    private function provider(): string
    {
        return strtolower(trim((string) AdminSetting::getValue('sys_sms_provider', 'none')));
    }

    private function apiKey(): string
    {
        return trim((string) AdminSetting::getValue('sys_sms_api_key', ''));
    }

    private function senderId(): string
    {
        return trim((string) AdminSetting::getValue('sys_sms_sender_id', ''));
    }

    private function templateId(): string
    {
        return trim((string) AdminSetting::getValue('sys_sms_template_id', ''));
    }

    private function normalizeIndianPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!is_string($digits) || $digits === '') {
            return '';
        }

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return preg_match('/^[6-9][0-9]{9}$/', $digits) === 1 ? $digits : '';
    }

    private function buildRechargeMessage(string $status, string $provider, float $amount, string $reference): string
    {
        $providerLabel = trim($provider) !== '' ? $provider : 'your operator';
        $formattedAmount = number_format($amount, 2, '.', '');

        return match ($status) {
            'success' => "Recharge successful. Rs {$formattedAmount} for {$providerLabel}. Ref: {$reference}.",
            'pending' => "Recharge pending. Rs {$formattedAmount} for {$providerLabel}. Ref: {$reference}.",
            'failed' => "Recharge failed. Rs {$formattedAmount} for {$providerLabel}. Ref: {$reference}.",
            default => '',
        };
    }

    private function sendViaMsg91(string $phone, string $message): void
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return;
        }

        Http::asForm()
            ->timeout(20)
            ->post('https://api.msg91.com/api/v2/sendsms', array_filter([
                'authkey' => $apiKey,
                'mobiles' => '91' . $phone,
                'message' => $message,
                'sender' => $this->senderId() !== '' ? $this->senderId() : null,
                'route' => '4',
                'DLT_TE_ID' => $this->templateId() !== '' ? $this->templateId() : null,
            ], static fn ($value) => $value !== null && $value !== ''));
    }

    private function sendViaFast2Sms(string $phone, string $message): void
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return;
        }

        Http::withHeaders([
            'authorization' => $apiKey,
            'accept' => 'application/json',
        ])->asForm()
            ->timeout(20)
            ->post('https://www.fast2sms.com/dev/bulkV2', array_filter([
                'route' => $this->templateId() !== '' ? 'dlt' : 'q',
                'message' => $message,
                'numbers' => $phone,
                'sender_id' => $this->senderId() !== '' ? $this->senderId() : null,
                'flash' => '0',
                'template_id' => $this->templateId() !== '' ? $this->templateId() : null,
            ], static fn ($value) => $value !== null && $value !== ''));
    }
}
