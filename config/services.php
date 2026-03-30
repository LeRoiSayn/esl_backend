<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file stores credentials for third-party services such as payment
    | gateways. Using config() instead of env() in application code ensures
    | compatibility with config caching (php artisan config:cache).
    |
    */

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY', ''),
        'public_key' => env('PAYSTACK_PUBLIC_KEY', ''),
    ],

    'flutterwave' => [
        'secret_key' => env('FLW_SECRET_KEY', ''),
        'public_key' => env('FLW_PUBLIC_KEY', ''),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'secret' => env('PAYPAL_SECRET', ''),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
    ],

    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173')),

];
