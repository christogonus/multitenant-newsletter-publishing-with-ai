<?php

use App\Enums\Product;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Monolog\Handler\NullHandler;
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
 * A LaunchpadJV IPN body, shaped like a real sale.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ipnPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'user_id' => 129,
        'product_id' => 3001,
        'offer_id' => null,
        'vendor_id' => 2,
        'transaction_id' => 'pi_1001',
        'total_amount' => '17.00',
        'bump' => 'false',
        'action' => 'SALE',
        'status' => 'success',
        'checkout_name' => 'Pat Buyer',
        'checkout_email' => 'buyer@example.com',
        'user' => ['id' => 129, 'name' => 'Pat Buyer', 'email' => 'buyer@example.com'],
    ], $overrides);
}

/**
 * Posts an IPN the way LaunchpadJV does: JSON, with the secret key in the address.
 *
 * @param  array<string, mixed>  $payload
 */
function postIpn(array $payload, string $key = 'test-webhook-key'): TestResponse
{
    return test()->postJson(appUrl('ipn/launchpad?key='.$key), $payload);
}

/**
 * Point every product at a predictable LaunchpadJV product ID: front_end => 3001, bump_vault => 3002, oto1_delivery => 3003, ...
 */
function configureLaunchpad(): void
{
    config([
        'products.launchpad.webhook_key' => 'test-webhook-key',
        'logging.channels.launchpad' => ['driver' => 'monolog', 'handler' => NullHandler::class],
    ]);

    foreach (Product::cases() as $index => $product) {
        config(["products.products.{$product->value}.launchpad_ids" => [(string) (3001 + $index)]]);
        config(["products.products.{$product->value}.checkout_url" => 'https://checkout.test/'.$product->value]);
    }
}

/**
 * The LaunchpadJV product ID configureLaunchpad() gives a product.
 */
function launchpadId(Product $product): int
{
    return 3001 + array_search($product, Product::cases(), true);
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
