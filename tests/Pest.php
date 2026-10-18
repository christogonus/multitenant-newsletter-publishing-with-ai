<?php

use App\Enums\Product;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DuskTestCase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(DuskTestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * A WarriorPlus IPN body using the field names from config/products.php.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ipnPayload(array $overrides = []): array
{
    $fields = config('products.warriorplus.fields');

    $payload = [
        $fields['security_key'] => 'test-security-key',
        $fields['action'] => 'sale',
        $fields['product_id'] => 'wso_fe',
        $fields['transaction_id'] => 'TXN-1001',
        $fields['sale_id'] => 'SALE-1001',
        $fields['buyer_email'] => 'buyer@example.com',
        $fields['buyer_name'] => 'Pat Buyer',
        $fields['amount'] => '17.00',
    ];

    foreach ($overrides as $key => $value) {
        $payload[$fields[$key] ?? $key] = $value;
    }

    return $payload;
}

/**
 * Point every product at a predictable WarriorPlus product ID: front_end => wso_fe, oto1_pro => wso_oto1_pro, ...
 */
function configureWarriorPlus(): void
{
    config(['products.warriorplus.security_key' => 'test-security-key']);

    foreach (Product::cases() as $product) {
        $id = $product === Product::FrontEnd ? 'wso_fe' : 'wso_'.$product->value;
        config(["products.products.{$product->value}.wp_product_ids" => [$id]]);
        config(["products.products.{$product->value}.checkout_url" => 'https://checkout.test/'.$product->value]);
    }
}

function appUrl(string $path = ''): string
{
    return 'https://'.config('newspub.app_host').'/'.ltrim($path, '/');
}

function publicationUrl(Newsletter $newsletter, string $path = ''): string
{
    return 'https://'.$newsletter->subdomain.'.'.config('newspub.public_root').'/'.ltrim($path, '/');
}

function buyer(Product ...$products): User
{
    return User::factory()->owning(...$products)->create();
}
