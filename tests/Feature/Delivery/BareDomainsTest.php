<?php

use App\Enums\Product;
use App\Enums\TlsStatus;
use App\Jobs\RequestDomainCertificateJob;
use App\Livewire\Design\Index as Design;
use App\Models\Domain;
use App\Models\Newsletter;
use App\Support\DomainVerifier;
use App\Support\Hostnames;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\FakeDnsVerifier;

beforeEach(fn () => config(['newspub.certificates.server_ips' => ['203.0.113.10']]));

test('typed addresses are tidied and bare domains are recognised', function (string $typed, string $host, bool $apex) {
    expect(Hostnames::normalize($typed))->toBe($host)
        ->and(Hostnames::isApex($host))->toBe($apex);
})->with([
    ['TheirSite.com', 'theirsite.com', true],
    ['https://www.theirsite.com/blog?x=1', 'theirsite.com', true],
    ['theirsite.co.uk', 'theirsite.co.uk', true],
    ['news.theirsite.com', 'news.theirsite.com', false],
    ['blog.theirsite.co.uk.', 'blog.theirsite.co.uk', false],
    ['a.b.theirsite.com', 'a.b.theirsite.com', false],
]);

test('record names are relative to the registrable domain', function () {
    expect(Hostnames::recordName('theirsite.com'))->toBe('@')
        ->and(Hostnames::recordName('theirsite.com', '_newspub'))->toBe('_newspub')
        ->and(Hostnames::recordName('news.theirsite.com'))->toBe('news')
        ->and(Hostnames::recordName('news.theirsite.com', '_newspub'))->toBe('_newspub.news')
        ->and(Hostnames::recordName('a.b.theirsite.co.uk'))->toBe('a.b');
});

test('a bare domain asks for an A record to our server, www as a CNAME, and the ownership TXT', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto8Premium))->create();
    $this->actingAs($newsletter->user);

    Livewire::test(Design::class)
        ->set('hostname', 'www.TheirSite.com')
        ->call('saveDomain')
        ->assertHasNoErrors()
        ->assertSee('203.0.113.10')
        ->assertSee('forwards to it');

    $domain = Domain::sole();
    expect($domain->hostname)->toBe('theirsite.com')->and($domain->is_apex)->toBeTrue();

    $records = collect($domain->setupRecords())->map(fn ($record) => [$record['type'], $record['name'], $record['value']])->all();
    expect($records)->toBe([
        ['A', '@', '203.0.113.10'],
        ['CNAME', 'www', config('newspub.public_root')],
        ['TXT', '_newspub', 'newspub-verify='.$domain->verification_token],
    ]);
});

test('a subdomain asks for a CNAME with the right name and the ownership TXT', function () {
    $domain = Domain::create(['newsletter_id' => Newsletter::factory()->create()->id, 'hostname' => 'news.theirsite.com', 'is_apex' => false, 'verification_token' => 'tok']);

    expect(collect($domain->setupRecords())->map(fn ($record) => [$record['type'], $record['name']])->all())
        ->toBe([['CNAME', 'news'], ['TXT', '_newspub.news']]);
});

test('a bare domain is only made primary once ownership, the A record and www all check out', function () {
    $domain = Domain::create(['newsletter_id' => Newsletter::factory()->create()->id, 'hostname' => 'theirsite.com', 'is_apex' => true, 'verification_token' => 'tok']);
    $txt = ['_newspub.theirsite.com' => ['newspub-verify=tok']];

    $onlyTxt = new FakeDnsVerifier(txt: $txt);
    expect($onlyTxt->verify($domain))->toBeFalse()
        ->and($domain->fresh()->dns_checks)->toBe(['ownership' => true, 'points_to_us' => false, 'www_points_to_us' => false]);

    $wrongIp = new FakeDnsVerifier(txt: $txt, a: ['theirsite.com' => ['198.51.100.7']], cname: ['www.theirsite.com' => [config('newspub.public_root')]]);
    expect($wrongIp->verify($domain))->toBeFalse();

    $apexCname = new FakeDnsVerifier(txt: $txt, cname: ['theirsite.com' => [config('newspub.public_root')], 'www.theirsite.com' => [config('newspub.public_root')]]);
    expect($apexCname->verify($domain))->toBeFalse();

    $all = new FakeDnsVerifier(txt: $txt, a: ['theirsite.com' => ['203.0.113.10']], cname: ['www.theirsite.com' => [config('newspub.public_root').'.']]);
    expect($all->verify($domain))->toBeTrue()
        ->and($domain->fresh()->verified_at)->not->toBeNull()
        ->and($domain->fresh()->dns_checks)->toBe(['ownership' => true, 'points_to_us' => true, 'www_points_to_us' => true]);
});

test('www may also point to our server with an A record', function () {
    $domain = Domain::create(['newsletter_id' => Newsletter::factory()->create()->id, 'hostname' => 'theirsite.com', 'is_apex' => true, 'verification_token' => 'tok']);

    $verifier = new FakeDnsVerifier(
        txt: ['_newspub.theirsite.com' => ['newspub-verify=tok']],
        a: ['theirsite.com' => ['203.0.113.10'], 'www.theirsite.com' => ['203.0.113.10']],
    );

    expect($verifier->verify($domain))->toBeTrue();
});

test('a subdomain must point to us as well as prove ownership', function () {
    $domain = Domain::create(['newsletter_id' => Newsletter::factory()->create()->id, 'hostname' => 'news.theirsite.com', 'verification_token' => 'tok']);
    $txt = ['_newspub.news.theirsite.com' => ['newspub-verify=tok']];

    expect((new FakeDnsVerifier(txt: $txt))->verify($domain))->toBeFalse()
        ->and($domain->fresh()->dns_checks)->toBe(['ownership' => true, 'points_to_us' => false]);

    expect((new FakeDnsVerifier(txt: $txt, a: ['news.theirsite.com' => ['203.0.113.10']]))->verify($domain))->toBeTrue();
});

test('the owner sees which record is still missing', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto8Premium))->create();
    Domain::create(['newsletter_id' => $newsletter->id, 'hostname' => 'theirsite.com', 'is_apex' => true, 'verification_token' => 'tok']);
    $this->app->instance(DomainVerifier::class, new FakeDnsVerifier(txt: ['_newspub.theirsite.com' => ['newspub-verify=tok']]));
    $this->actingAs($newsletter->user);

    Livewire::test(Design::class)
        ->call('verifyDomain')
        ->assertDispatched('toast', tone: 'warning')
        ->assertSee('Found')
        ->assertSee('Not found yet');
});

test('the certificate for a bare domain also covers www', function () {
    config([
        'newspub.certificates.driver' => 'ploi',
        'newspub.certificates.ploi' => ['token' => 't', 'server_id' => '1', 'site_id' => '2'],
    ]);
    Http::fake([
        'ploi.io/api/servers/1/sites/2/tenants' => Http::response(['data' => ['tenants' => ['theirsite.com']]]),
        'ploi.io/api/servers/1/sites/2/tenants/theirsite.com/request-certificate' => Http::response(null, 201),
        'https://theirsite.com/' => Http::failedConnection(),
    ]);
    $domain = Domain::create(['newsletter_id' => Newsletter::factory()->create()->id, 'hostname' => 'theirsite.com', 'is_apex' => true, 'verification_token' => 'tok', 'verified_at' => now()]);

    RequestDomainCertificateJob::dispatchSync($domain);

    expect($domain->fresh()->tls_status)->toBe(TlsStatus::Requested);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/request-certificate') && $request['domains'] === 'theirsite.com,www.theirsite.com');
});

test('www of a verified bare domain forwards to the bare domain', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto8Premium))->create(['name' => 'Bare Weekly']);
    Domain::create(['newsletter_id' => $newsletter->id, 'hostname' => 'theirsite.com', 'is_apex' => true, 'verification_token' => 'tok', 'verified_at' => now()]);

    $this->get('https://theirsite.com/')->assertOk()->assertSee('Bare Weekly');
    $this->get('https://www.theirsite.com/archive?page=2')->assertRedirect('https://theirsite.com/archive?page=2')->assertStatus(301);
    $this->get(appUrl('internal/domains/allowed?domain=www.theirsite.com'))->assertOk();
    $this->get(appUrl('internal/domains/allowed?domain=www.unknown-site.com'))->assertNotFound();
});

test('www of a subdomain is not treated as part of it', function () {
    $newsletter = Newsletter::factory()->create();
    Domain::create(['newsletter_id' => $newsletter->id, 'hostname' => 'news.theirsite.com', 'is_apex' => false, 'verification_token' => 'tok', 'verified_at' => now()]);

    $this->get('https://www.news.theirsite.com/')->assertNotFound();
});
