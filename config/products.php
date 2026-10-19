<?php

/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
|
| Maps each feature key to its LaunchpadJV product IDs (comma separated in
| the environment, so test and live products can both unlock a feature)
| and to the checkout URL used by the locked-feature Upgrade buttons.
|
| An ID is either "product_id" or "product_id:offer_id", the second for
| when two of our products are offers of one LaunchpadJV product.
|
*/

$product = fn (string $envKey): array => [
    'launchpad_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env("LAUNCHPAD_PRODUCT_{$envKey}", ''))))),
    'checkout_url' => env("CHECKOUT_{$envKey}", '#'),
];

return [

    'launchpad' => [
        /*
         * LaunchpadJV does not sign its IPNs, so the IPN address carries this secret instead:
         * POST {APP_URL}/ipn/launchpad?key=LAUNCHPAD_WEBHOOK_KEY (or an X-Webhook-Key header).
         */
        'webhook_key' => env('LAUNCHPAD_WEBHOOK_KEY'),

        /*
         * IPN "action" values (compared case-insensitively) mapped to ledger actions.
         */
        'actions' => [
            'sale' => 'sale',
            'purchase' => 'sale',
            'refund' => 'refund',
            'chargeback' => 'chargeback',
        ],
    ],

    'products' => [
        'front_end' => $product('FRONT_END'),
        'bump_vault' => $product('BUMP_VAULT'),
        'oto1_delivery' => $product('OTO1_DELIVERY'),
        'oto2_silver' => $product('OTO2_SILVER'),
        'oto2_gold' => $product('OTO2_GOLD'),
        'oto3_growth' => $product('OTO3_GROWTH'),
        'oto4_sponsor' => $product('OTO4_SPONSOR'),
        'oto5_dfy' => $product('OTO5_DFY'),
        'oto6_social' => $product('OTO6_SOCIAL'),
        'oto7_autopilot' => $product('OTO7_AUTOPILOT'),
        'oto8_premium' => $product('OTO8_PREMIUM'),
        'oto9_multi' => $product('OTO9_MULTI'),
        'oto10_network' => $product('OTO10_NETWORK'),
        'oto11_agency' => $product('OTO11_AGENCY'),
        'oto12_reseller' => $product('OTO12_RESELLER'),
    ],

];
