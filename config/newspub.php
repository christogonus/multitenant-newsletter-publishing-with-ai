<?php

$appHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';

return [

    /*
    |--------------------------------------------------------------------------
    | Hosts
    |--------------------------------------------------------------------------
    |
    | The dashboard lives on the app host. Publications live on subdomains of
    | the public root ({subdomain}.public_root). Any other host is treated as
    | a customer domain (OTO 8) and resolved through the domains table.
    |
    */

    'app_host' => env('NEWSPUB_APP_HOST', $appHost),

    'public_root' => env('NEWSPUB_PUBLIC_ROOT', $appHost),

    'public_scheme' => env('NEWSPUB_PUBLIC_SCHEME', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_SCHEME) ?: 'https'),

    /*
     * Subdomains no publication may claim.
     */
    'reserved_subdomains' => ['www', 'app', 'admin', 'api', 'mail', 'help', 'support', 'blog', 'status', 'static', 'cdn', 'internal'],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'weekly_issues' => (int) env('NEWSPUB_WEEKLY_ISSUES', 15),

        /*
         * Hidden fair-use ceiling for OTO 2 "unlimited". Written into the terms.
         */
        'fair_use_weekly_issues' => (int) env('NEWSPUB_FAIR_USE_WEEKLY_ISSUES', 150),

        'publications' => [
            'base' => 1,
            'oto5_dfy' => 5,
            'oto9_multi' => null,
        ],

        'connections' => [
            'oto1_lite' => 1,
            'oto1_pro' => null,
        ],

        'reseller_codes_per_license' => (int) env('NEWSPUB_RESELLER_CODES', 100),

        'bulk_generation_max' => 31,

        'subscribe_per_minute' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI
    |--------------------------------------------------------------------------
    |
    | Provider and model are read by the agents. Prices are USD per million
    | tokens and feed the ai_usage cost column and the spend alert.
    |
    */

    'ai' => [
        'provider' => env('NEWSPUB_AI_PROVIDER', 'anthropic'),

        /*
         * Use the deterministic App\Ai\FakeNewsletterWriter (browser tests, demos without a key). Never in production.
         */
        'fake' => (bool) env('NEWSPUB_AI_FAKE', false) && env('APP_ENV') !== 'production',
        'model' => env('NEWSPUB_AI_MODEL'),
        'research_enabled' => (bool) env('NEWSPUB_AI_RESEARCH', true),
        'research_cache_hours' => (int) env('NEWSPUB_RESEARCH_CACHE_HOURS', 6),
        'price_per_million_input' => (float) env('NEWSPUB_AI_PRICE_INPUT', 3.00),
        'price_per_million_output' => (float) env('NEWSPUB_AI_PRICE_OUTPUT', 15.00),
        'daily_spend_alert' => (float) env('NEWSPUB_AI_DAILY_SPEND_ALERT', 50),
    ],

    'support_email' => env('NEWSPUB_SUPPORT_EMAIL', 'support@example.com'),

    /*
     * Where Horizon long-wait alerts go. Admins also get AI spend alerts by email.
     */
    'alert_email' => env('NEWSPUB_ALERT_EMAIL'),

];
