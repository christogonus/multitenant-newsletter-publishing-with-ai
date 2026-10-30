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
     * Subdomains no publication may claim. Admins add more under Admin, Reserved addresses.
     */
    'reserved_subdomains' => [
        'www', 'app', 'admin', 'api', 'mail', 'help', 'support', 'blog', 'status', 'static', 'cdn', 'internal',
        'email', 'webmail', 'noreply', 'no-reply', 'cpanel', 'whm', 'webdisk', 'smtp', 'imap', 'pop', 'pop3', 'ftp',
        'autodiscover', 'autoconfig', 'billing', 'login', 'account', 'dashboard',
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        /*
         * AI-written issues per newsletter per rolling week, before OTO 2.
         */
        'weekly_issues' => (int) env('NEWSPUB_WEEKLY_ISSUES', 15),

        /*
         * Hidden fair-use ceiling for OTO 2 "unlimited", across all of a buyer's newsletters. Written into the terms.
         */
        'fair_use_weekly_issues' => (int) env('NEWSPUB_FAIR_USE_WEEKLY_ISSUES', 150),

        /*
         * Newsletter brands a buyer may run. OTO 2 (Silver or Gold) and the agency license remove the cap.
         */
        'publications' => [
            'base' => 3,
            'oto5_dfy' => 5,
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

        /*
         * Used for issues written for OTO 2 Gold buyers. Empty means the normal model.
         */
        'premium_model' => env('NEWSPUB_AI_PREMIUM_MODEL'),
        /*
         * How hard OpenAI reasoning models think: minimal, low, medium or high. Empty sends nothing.
         */
        'reasoning_effort' => env('NEWSPUB_AI_REASONING_EFFORT', 'low'),
        'research_enabled' => (bool) env('NEWSPUB_AI_RESEARCH', true),
        'research_cache_hours' => (int) env('NEWSPUB_RESEARCH_CACHE_HOURS', 6),
        'price_per_million_input' => (float) env('NEWSPUB_AI_PRICE_INPUT', 3.00),
        'price_per_million_output' => (float) env('NEWSPUB_AI_PRICE_OUTPUT', 15.00),
        'daily_spend_alert' => (float) env('NEWSPUB_AI_DAILY_SPEND_ALERT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom domain certificates (OTO 8)
    |--------------------------------------------------------------------------
    |
    | "ploi": each verified domain becomes a tenant of the app's Ploi site and gets its own
    | Let's Encrypt certificate through the Ploi API. "external": the web server issues
    | certificates itself (Caddy on-demand TLS asking /internal/domains/allowed) or none (local).
    |
    */

    'certificates' => [
        'driver' => env('NEWSPUB_CERTIFICATES', 'external'),

        /*
         * Public IP address(es) of the web server, comma separated. Owners of bare domains
         * (theirsite.com) point an A record here; verification checks it.
         */
        'server_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('NEWSPUB_SERVER_IP', ''))))),

        'ploi' => [
            'token' => env('PLOI_API_TOKEN'),
            'server_id' => env('PLOI_SERVER_ID'),
            'site_id' => env('PLOI_SITE_ID'),
        ],
    ],

    'support_email' => env('NEWSPUB_SUPPORT_EMAIL', 'support@example.com'),

    /*
     * The password every new buyer account starts with. It is printed in the welcome email and on the
     * public /support page, so buyers whose inbox rejects our email can still sign in. Empty means new
     * buyers get a set-password link instead.
     */
    'default_password' => env('NEWSPUB_DEFAULT_PASSWORD'),

    /*
     * Where Horizon long-wait alerts go. Admins also get AI spend alerts by email.
     */
    'alert_email' => env('NEWSPUB_ALERT_EMAIL'),

    /*
     * The Newsletter Trend Radar bonus: 12 monthly editions, Edition 1 in the starting month (it ships with the
     * bonus pack). From Edition 2 on, the admin area reminds admins to write each edition and email it to buyers.
     */
    'trend_radar' => [
        'starts' => env('NEWSPUB_TREND_RADAR_START', '2026-11-01'),
        'editions' => 12,
    ],

];
