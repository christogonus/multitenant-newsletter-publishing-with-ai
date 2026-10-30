<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

test('a reset link cannot be pointed at another site with forwarded headers', function (string $header, string $value) {
    config(['mail.default' => 'array']);
    User::factory()->create(['email' => 'victim@example.com']);

    $this->withHeaders([$header => $value])->post(appUrl('forgot-password'), ['email' => 'victim@example.com']);

    $html = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getHtmlBody();
    preg_match('#https?://[^"\s]+reset-password/[^"\s]+#', $html, $matches);

    expect(parse_url(htmlspecialchars_decode($matches[0]), PHP_URL_HOST))->toBe(config('newspub.app_host'));
})->with([
    'prefix' => ['X-Forwarded-Prefix', '@evil.example'],
    'host' => ['X-Forwarded-Host', 'evil.example'],
]);

test('a faked client IP does not get around the login limit', function () {
    User::factory()->create(['email' => 'victim@example.com']);

    $statuses = collect(range(1, 7))->map(fn (int $attempt) => $this
        ->withHeaders(['X-Forwarded-For' => "1.2.3.{$attempt}"])
        ->post(appUrl('login'), ['email' => 'victim@example.com', 'password' => 'wrong'.$attempt])
        ->status());

    expect($statuses->last())->toBe(429);
});

test('guessing one account from many real addresses still hits the per-account limit', function () {
    User::factory()->create(['email' => 'victim@example.com']);

    $statuses = collect(range(1, 21))->map(fn (int $attempt) => $this
        ->withServerVariables(['REMOTE_ADDR' => "1.2.3.{$attempt}"])
        ->post(appUrl('login'), ['email' => 'victim@example.com', 'password' => 'wrong'.$attempt])
        ->status());

    expect($statuses->take(20)->contains(429))->toBeFalse()
        ->and($statuses->last())->toBe(429);
});

test('a proxy listed in TRUSTED_PROXIES still passes on the real client IP', function () {
    config(['trustedproxy.proxies' => '10.0.0.1']);
    $seen = null;
    Route::get('/test-client-ip', function (Request $request) use (&$seen) {
        $seen = $request->ip();
    })->middleware('web');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
        ->get('/test-client-ip');

    expect($seen)->toBe('203.0.113.9');
});
