<?php

use App\Enums\Product;
use App\Enums\TlsStatus;
use App\Jobs\RefreshDomainCertificateJob;
use App\Jobs\RequestDomainCertificateJob;
use App\Livewire\Design\Index as Design;
use App\Models\Domain;
use App\Models\Newsletter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'newspub.certificates.driver' => 'ploi',
        'newspub.certificates.ploi' => ['token' => 'ploi-token', 'server_id' => '11', 'site_id' => '22'],
    ]);
    Http::preventStrayRequests();
});

function verifiedDomain(array $attributes = []): Domain
{
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto8Premium))->create();

    return Domain::create([
        'newsletter_id' => $newsletter->id,
        'hostname' => 'news.example.com',
        'verification_token' => 'tok',
        'verified_at' => now(),
        ...$attributes,
    ]);
}

test('a verified domain becomes a ploi tenant and gets a certificate request with a signed callback', function () {
    Http::fake([
        'ploi.io/api/servers/11/sites/22/tenants' => Http::sequence()
            ->push(['data' => ['tenants' => [], 'count' => 0, 'main' => 'app.example']])
            ->push(['data' => ['tenants' => ['news.example.com'], 'count' => 1, 'main' => 'app.example']]),
        'ploi.io/api/servers/11/sites/22/tenants/news.example.com/request-certificate' => Http::response(null, 201),
        'https://news.example.com/' => Http::failedConnection(),
    ]);
    $domain = verifiedDomain();

    RequestDomainCertificateJob::dispatchSync($domain);

    $domain->refresh();
    expect($domain->tls_status)->toBe(TlsStatus::Requested)
        ->and($domain->tenant_created)->toBeTrue()
        ->and($domain->tls_attempts)->toBe(1);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/tenants')
        && $request['tenants'] === ['news.example.com']
        && $request->hasHeader('Authorization', 'Bearer ploi-token'));

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/request-certificate')
        && $request['domains'] === 'news.example.com'
        && str_contains($request['webhook'], '/internal/domains/'.$domain->id.'/certificate-callback')
        && str_contains($request['webhook'], 'signature='));
});

test('an existing tenant is not added twice', function () {
    Http::fake([
        'ploi.io/api/servers/11/sites/22/tenants' => Http::response(['data' => ['tenants' => ['news.example.com'], 'count' => 1, 'main' => 'app.example']]),
        'ploi.io/api/servers/11/sites/22/tenants/news.example.com/request-certificate' => Http::response(null, 201),
        'https://news.example.com/' => Http::failedConnection(),
    ]);

    RequestDomainCertificateJob::dispatchSync(verifiedDomain());

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/tenants'));
});

test('a refused certificate request shows the owner a plain reason', function () {
    Http::fake([
        'ploi.io/api/servers/11/sites/22/tenants' => Http::response(['data' => ['tenants' => ['news.example.com']]]),
        'ploi.io/api/servers/11/sites/22/tenants/news.example.com/request-certificate' => Http::response(['message' => 'The domain does not point to this server.'], 422),
    ]);
    $domain = verifiedDomain();

    RequestDomainCertificateJob::dispatchSync($domain);

    $domain->refresh();
    expect($domain->tls_status)->toBe(TlsStatus::Failed)
        ->and($domain->tls_error)->toContain('does not point to this server')
        ->and($domain->canRequestCertificate())->toBeTrue();
});

test('the certificate becomes active once https really works, with the expiry from ploi', function () {
    Http::fake([
        'https://news.example.com/' => Http::response('ok'),
        'ploi.io/api/servers/11/sites/22/certificates*' => Http::response([
            'data' => [['id' => 5, 'status' => 'active', 'domain' => 'news.example.com', 'expires_at' => '2027-01-15 10:00:00']],
            'meta' => ['last_page' => 1],
        ]),
    ]);
    $domain = verifiedDomain(['tls_status' => TlsStatus::Requested, 'tls_requested_at' => now()->subMinutes(2)]);

    RefreshDomainCertificateJob::dispatchSync($domain);

    $domain->refresh();
    expect($domain->tls_status)->toBe(TlsStatus::Active)
        ->and($domain->tls_issued_at)->not->toBeNull()
        ->and($domain->tls_expires_at->toDateString())->toBe('2027-01-15');
});

test('a request that never completes is reported as failed after the timeout', function () {
    Http::fake(['https://news.example.com/' => Http::failedConnection()]);
    $waiting = verifiedDomain(['tls_status' => TlsStatus::Requested, 'tls_requested_at' => now()->subMinutes(5)]);

    RefreshDomainCertificateJob::dispatchSync($waiting);
    expect($waiting->fresh()->tls_status)->toBe(TlsStatus::Requested);

    $waiting->update(['tls_requested_at' => now()->subMinutes(Domain::CERTIFICATE_TIMEOUT_MINUTES + 1)]);
    RefreshDomainCertificateJob::dispatchSync($waiting);

    expect($waiting->fresh())->tls_status->toBe(TlsStatus::Failed)
        ->and($waiting->fresh()->tls_error)->toContain('does not point to us');
});

test('a working domain that stops opening securely is flagged to the owner', function () {
    Http::fake(['https://news.example.com/' => Http::failedConnection()]);
    $domain = verifiedDomain(['tls_status' => TlsStatus::Active, 'tls_issued_at' => now()->subMonth()]);

    RefreshDomainCertificateJob::dispatchSync($domain);

    expect($domain->fresh()->tls_status)->toBe(TlsStatus::Failed);
});

test('the owner sees the status and can request again after a failure, within a limit', function () {
    Http::fake([
        'ploi.io/api/servers/11/sites/22/tenants' => Http::response(['data' => ['tenants' => ['news.example.com']]]),
        'ploi.io/api/servers/11/sites/22/tenants/news.example.com/request-certificate' => Http::response(null, 201),
        'https://news.example.com/' => Http::failedConnection(),
    ]);
    $domain = verifiedDomain(['tls_status' => TlsStatus::Failed, 'tls_error' => 'It failed.']);
    $this->actingAs($domain->newsletter->user);

    Livewire::test(Design::class)
        ->assertSee('Secure connection (https)')
        ->assertSee('Not secure yet')
        ->assertSee('It failed.')
        ->assertSee('Request again')
        ->call('requestCertificate')
        ->assertDispatched('toast', tone: 'success');

    expect($domain->fresh()->tls_status)->toBe(TlsStatus::Requested);

    foreach (range(1, Domain::MAX_REQUESTS_PER_HOUR) as $attempt) {
        $domain->refresh()->update(['tls_status' => TlsStatus::Failed]);
        Livewire::test(Design::class)->call('requestCertificate');
    }

    $domain->refresh()->update(['tls_status' => TlsStatus::Failed]);

    Livewire::test(Design::class)->call('requestCertificate')->assertDispatched('toast', tone: 'warning');
});

test('an in-progress request cannot be repeated until it has been stuck a while', function () {
    $domain = verifiedDomain(['tls_status' => TlsStatus::Requested, 'tls_requested_at' => now()->subMinutes(2)]);
    expect($domain->canRequestCertificate())->toBeFalse();

    $domain->update(['tls_requested_at' => now()->subMinutes(20)]);
    expect($domain->fresh()->canRequestCertificate())->toBeTrue();
});

test('the owner can check the status on demand', function () {
    Http::fake([
        'https://news.example.com/' => Http::response('ok'),
        'ploi.io/api/servers/11/sites/22/certificates*' => Http::response(['data' => [], 'meta' => ['last_page' => 1]]),
    ]);
    $domain = verifiedDomain(['tls_status' => TlsStatus::Requested, 'tls_requested_at' => now()]);
    $this->actingAs($domain->newsletter->user);

    Livewire::test(Design::class)->call('checkCertificate')->assertSee('Secure');

    expect($domain->fresh()->tls_status)->toBe(TlsStatus::Active);
});

test('ploi calls the signed callback and the status is refreshed', function () {
    Http::fake([
        'https://news.example.com/' => Http::response('ok'),
        'ploi.io/api/servers/11/sites/22/certificates*' => Http::response(['data' => [], 'meta' => ['last_page' => 1]]),
    ]);
    $domain = verifiedDomain(['tls_status' => TlsStatus::Requested, 'tls_requested_at' => now()]);

    $this->post(RequestDomainCertificateJob::callbackUrl($domain), ['anything' => 'ignored'])->assertOk();
    expect($domain->fresh()->tls_status)->toBe(TlsStatus::Active);

    $this->post(appUrl('internal/domains/'.$domain->id.'/certificate-callback'))->assertForbidden();
});

test('removing a domain removes its ploi tenant', function () {
    Http::fake(['ploi.io/api/servers/11/sites/22/tenants/news.example.com' => Http::response(null, 200)]);
    $domain = verifiedDomain(['tenant_created' => true, 'tls_status' => TlsStatus::Active]);
    $this->actingAs($domain->newsletter->user);

    Livewire::test(Design::class)->call('removeDomain');

    expect(Domain::count())->toBe(0);
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/tenants/news.example.com'));
});

test('deleting a publication removes its ploi tenant too', function () {
    Http::fake(['ploi.io/api/servers/11/sites/22/tenants/news.example.com' => Http::response(null, 404)]);
    $domain = verifiedDomain(['tenant_created' => true]);

    $domain->newsletter->delete();

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE');
});

test('without ploi the server handles certificates and the owner still sees https status', function () {
    config(['newspub.certificates.driver' => 'external']);
    Http::fake(['https://news.example.com/' => Http::response('ok')]);
    $domain = verifiedDomain();

    RequestDomainCertificateJob::dispatchSync($domain);

    expect($domain->fresh()->tls_status)->toBe(TlsStatus::External);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'ploi.io'));
});

test('the scheduled check refreshes requests in progress and stale working domains', function () {
    Queue::fake();
    verifiedDomain(['tls_status' => TlsStatus::Requested, 'tls_requested_at' => now()]);
    $fresh = Newsletter::factory()->create();
    Domain::create(['newsletter_id' => $fresh->id, 'hostname' => 'fresh.example.com', 'verification_token' => 'x', 'verified_at' => now(), 'tls_status' => TlsStatus::Active, 'tls_checked_at' => now()]);

    $this->artisan('newspub:check-domain-certificates')->assertSuccessful();

    Queue::assertPushed(RefreshDomainCertificateJob::class, 1);
});
