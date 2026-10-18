<?php

/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
|
| Maps each feature key to its WarriorPlus product IDs (comma separated in
| the environment, so test and live products can both unlock a feature)
| and to the checkout URL used by the locked-feature Upgrade buttons.
|
*/

$product = fn (string $envKey): array => [
    'wp_product_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env("WP_PRODUCT_{$envKey}", ''))))),
    'checkout_url' => env("CHECKOUT_{$envKey}", '#'),
];

return [

    'warriorplus' => [
        'security_key' => env('WARRIORPLUS_SECURITY_KEY'),

        /*
         * IPN field names. Confirm these against the vendor account before launch.
         */
        'fields' => [
            'security_key' => 'WP_SECURITYKEY',
            'action' => 'WP_ACTION',
            'product_id' => 'WP_ITEM_NUMBER',
            'transaction_id' => 'WP_TXNID',
            'sale_id' => 'WP_SALEID',
            'buyer_email' => 'WP_BUYER_EMAIL',
            'buyer_name' => 'WP_BUYER_NAME',
            'amount' => 'WP_SALE_AMOUNT',
        ],

        /*
         * IPN action values mapped to ledger actions.
         */
        'actions' => [
            'sale' => 'sale',
            'refund' => 'refund',
            'chargeback' => 'chargeback',
            'reversal' => 'chargeback',
        ],
    ],

    'products' => [
        'front_end' => $product('FRONT_END'),
        'bump_vault' => $product('BUMP_VAULT'),
        'oto1_lite' => $product('OTO1_LITE'),
        'oto1_pro' => $product('OTO1_PRO'),
        'oto2_unlimited' => $product('OTO2_UNLIMITED'),
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
