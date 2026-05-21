<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Purchases Module Configuration
    |--------------------------------------------------------------------------
    |
    | @see CLAUDE.md للتفاصيل الكاملة
    */

    'exchange_rates' => [
        'openexchangerates' => [
            'api_key' => env('OPENEXCHANGERATES_API_KEY'),
            'base_url' => env('OPENEXCHANGERATES_BASE_URL', 'https://openexchangerates.org/api'),
        ],

        'frankfurter' => [
            'base_url' => env('FRANKFURTER_BASE_URL', 'https://api.frankfurter.app'),
        ],

        'timeout' => env('EXCHANGE_RATE_TIMEOUT', 10),
        'cache_ttl' => env('EXCHANGE_RATE_CACHE_TTL', 21600), // 6 ساعات
    ],

    'features' => [
        'auto_rate_update' => env('FEATURE_AUTO_RATE_UPDATE', true),
        'whatsapp_notifications' => env('FEATURE_WHATSAPP_NOTIFICATIONS', true),
        'spike_protection' => env('FEATURE_SPIKE_PROTECTION', true),
    ],

    'business' => [
        'default_buyer_max_order_value_usd' => env('DEFAULT_BUYER_MAX_ORDER_VALUE_USD', 5000),
        'default_buyer_min_balance_threshold_usd' => env('DEFAULT_BUYER_MIN_BALANCE_THRESHOLD_USD', 1000),
        'default_rate_update_interval_hours' => env('DEFAULT_RATE_UPDATE_INTERVAL_HOURS', 6),
        'default_max_rate_deviation_percent' => env('DEFAULT_MAX_RATE_DEVIATION_PERCENT', 5),
        'order_reservation_expiry_days' => env('ORDER_RESERVATION_EXPIRY_DAYS', 30),
        'return_window_days' => env('RETURN_WINDOW_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chart of Accounts mapping
    |--------------------------------------------------------------------------
    |
    | Codes here MUST exist in chart_of_accounts. The mapping below is aligned
    | with the existing ShipFlow chart — see docs/ALIGNMENT_PATCH.md §2.4 for
    | rationale. Override per env via .env only when introducing a new chart.
    */
    'accounts' => [
        'cash_bank'             => env('ACCOUNT_CASH_BANK',             '1000'), // existing: Cash on hand
        'customer_wallets'      => env('ACCOUNT_CUSTOMER_WALLETS',      '2000'), // existing: Client deposits (liability)
        'buyer_float'           => env('ACCOUNT_BUYER_FLOAT',           '1250'), // new (purchases)
        'purchases_in_transit'  => env('ACCOUNT_PURCHASES_IN_TRANSIT',  '1320'), // new (purchases)
        'goods_in_warehouse'    => env('ACCOUNT_GOODS_IN_WAREHOUSE',    '1400'), // new (purchases)
        'goods_in_shipment'     => env('ACCOUNT_GOODS_IN_SHIPMENT',     '1500'), // new (purchases)
        'customer_liability'    => env('ACCOUNT_CUSTOMER_LIABILITY',    '2000'), // same account as customer_wallets
        'commission_revenue'    => env('ACCOUNT_COMMISSION_REVENUE',    '4000'), // existing: Commission revenue
        'fx_gains'              => env('ACCOUNT_FX_GAINS',              '4200'), // new (purchases)
        'cogs'                  => env('ACCOUNT_COGS',                  '5400'), // new (purchases)
        'fx_losses'             => env('ACCOUNT_FX_LOSSES',             '5200'), // existing: FX gain/loss
    ],
];
