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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'msg91' => [
        'auth_key'          => env('MSG91_AUTH_KEY'),
        'otp_template_id'   => env('MSG91_OTP_TEMPLATE_ID'),
    ],

    'razorpay' => [
        'key_id'         => env('RAZORPAY_KEY_ID'),
        'key_secret'     => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'shiprocket' => [
        'email'           => env('SHIPROCKET_EMAIL'),
        'password'        => env('SHIPROCKET_PASSWORD'),
        'pickup_location' => env('SHIPROCKET_PICKUP_LOCATION', 'Primary'),
    ],

    'delhivery' => [
        'token'       => env('DELHIVERY_TOKEN'),
        'seller_name' => env('DELHIVERY_SELLER_NAME', 'DXEMPIRE'),
    ],

    'dtdc' => [
        'api_key'        => env('DTDC_API_KEY'),
        'customer_id'    => env('DTDC_CUSTOMER_ID'),
        'pickup_name'    => env('DTDC_PICKUP_NAME', 'DXEMPIRE'),
        'pickup_address' => env('DTDC_PICKUP_ADDRESS'),
        'pickup_pincode' => env('DTDC_PICKUP_PINCODE'),
    ],

    'twilio' => [
        'account_sid'    => env('TWILIO_ACCOUNT_SID'),
        'auth_token'     => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from'  => env('TWILIO_WHATSAPP_FROM', '+14155238886'),
    ],

    'interakt' => [
        'api_key' => env('INTERAKT_API_KEY'),
    ],

    'expo' => [
        // Optional — Expo push works without it, but Expo recommends setting
        // one for enhanced security (stops others from pushing to your project).
        // https://docs.expo.dev/push-notifications/sending-notifications/#additional-security
        'access_token' => env('EXPO_ACCESS_TOKEN'),
    ],

];
