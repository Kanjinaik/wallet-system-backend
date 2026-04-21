<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ertitech_payout' => [
        'base_url' => env('ERTITECH_PAYOUT_BASE_URL', 'https://api.ertipay.com/uat'),
        'username' => env('ERTITECH_PAYOUT_USERNAME'),
        'password' => env('ERTITECH_PAYOUT_PASSWORD'),
        'merchant_id' => env('ERTITECH_PAYOUT_MERCHANT_ID'),
        'wallet_id' => env('ERTITECH_PAYOUT_WALLET_ID'),
        'aes_key' => env('ERTITECH_PAYOUT_AES_KEY'),
        'preferred_bank' => env('ERTITECH_PAYOUT_PREFERRED_BANK', 'pnb'),
        'mode' => env('ERTITECH_PAYOUT_MODE', 'test'),
    ],

    'retailer_recharge' => [
        'provider' => env('RETAILER_RECHARGE_PROVIDER'),
        'base_url' => env('RETAILER_RECHARGE_BASE_URL', 'https://bbps-sb.payu.in'),
        'api_key' => env('RETAILER_RECHARGE_API_KEY'),
        'secret_key' => env('RETAILER_RECHARGE_SECRET_KEY'),
        'working_key' => env('RETAILER_RECHARGE_WORKING_KEY'),
        'username' => env('RETAILER_RECHARGE_USERNAME'),
        'password' => env('RETAILER_RECHARGE_PASSWORD'),
        'auth_base_url' => env('RETAILER_RECHARGE_AUTH_BASE_URL'),
        'grant_type' => env('RETAILER_RECHARGE_GRANT_TYPE', 'client_credentials'),
        'scope' => env('RETAILER_RECHARGE_SCOPE'),
        'mode' => env('RETAILER_RECHARGE_MODE', 'test'),
        'iv' => env('RETAILER_RECHARGE_IV'),
        'agent_id' => env('RETAILER_RECHARGE_AGENT_ID'),
        'channel' => env('RETAILER_RECHARGE_CHANNEL', 'INT'),
        'timeout_seconds' => env('RETAILER_RECHARGE_TIMEOUT_SECONDS', 300),
    ],

];
