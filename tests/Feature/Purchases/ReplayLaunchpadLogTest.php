<?php

use App\Enums\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\WelcomeBuyer;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    configureLaunchpad();
    Notification::fake();
});

/**
 * A launchpad.log line as the IPN controller writes it.
 *
 * @param  array<string, mixed>  $body
 * @param  array<string, string>  $headers
 */
function launchpadLogLine(array $body, array $headers = []): string
{
    $context = ['ip' => '65.108.6.37', 'method' => 'POST', 'query' => [], 'headers' => ['content-type' => 'application/json', ...$headers], 'body' => json_encode($body)];

    return '[2026-10-30 01:41:20] production.INFO: IPN received '.json_encode($context);
}

/**
 * @param  list<string>  $lines
 */
function launchpadLogFile(array $lines): string
{
    $path = tempnam(sys_get_temp_dir(), 'launchpad');
    file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);

    return $path;
}

test('replaying the log creates the buyers of sales that were never recorded, and a second run changes nothing', function () {
    $path = launchpadLogFile([
        launchpadLogLine(['id' => 128049, 'product_id' => 3001, 'offer_id' => 606, 'bump' => 'false', 'action' => 'SALE', 'status' => 'success', 'user' => ['id' => 8507, 'name' => 'Lee Live', 'email' => 'live-buyer@example.com']]),
        launchpadLogLine(['id' => 128050, 'product_id' => 3001, 'action' => 'SALE', 'user' => ['email' => 'replayed@example.com']], ['x-replayed-from-log' => '2026-10-30 01:41:20']),
        '[2026-10-30 01:42:00] production.INFO: something else {"body":"{}"}',
    ]);

    $this->artisan('launchpad:replay', ['path' => $path])->assertSuccessful();

    $buyer = User::sole();
    expect($buyer->email)->toBe('live-buyer@example.com')
        ->and($buyer->owns(Product::FrontEnd))->toBeTrue();
    Notification::assertSentTimes(WelcomeBuyer::class, 1);

    $this->artisan('launchpad:replay', ['path' => $path])->expectsOutputToContain('200 Duplicate')->assertSuccessful();

    expect(Purchase::count())->toBe(1);
    Notification::assertSentTimes(WelcomeBuyer::class, 1);
});

test('a fake sale sent with a wrong key is logged without its body and cannot be replayed', function () {
    $path = tempnam(sys_get_temp_dir(), 'launchpad');
    config(['logging.channels.launchpad' => ['driver' => 'single', 'path' => $path]]);

    postIpn(ipnPayload(['user' => ['email' => 'attacker@example.com']]), key: 'wrong-key')->assertForbidden();

    expect(file_get_contents($path))->toContain('IPN refused')->not->toContain('attacker@example.com');

    $this->artisan('launchpad:replay', ['path' => $path])->assertSuccessful();

    expect(User::count())->toBe(0);
});

test('a sale with the right key is logged in full and can be replayed', function () {
    $path = tempnam(sys_get_temp_dir(), 'launchpad');
    config(['logging.channels.launchpad' => ['driver' => 'single', 'path' => $path]]);

    postIpn(ipnPayload())->assertOk();
    Purchase::query()->delete();
    User::query()->delete();

    $this->artisan('launchpad:replay', ['path' => $path])->assertSuccessful();

    expect(User::sole()->email)->toBe('buyer@example.com');
});

test('a dry run records nothing', function () {
    $path = launchpadLogFile([
        launchpadLogLine(['id' => 128049, 'product_id' => 3001, 'action' => 'SALE', 'user' => ['email' => 'live-buyer@example.com']]),
    ]);

    $this->artisan('launchpad:replay', ['path' => $path, '--dry-run' => true])->expectsOutputToContain('1 IPN would be replayed')->assertSuccessful();

    expect(User::count())->toBe(0);
});
