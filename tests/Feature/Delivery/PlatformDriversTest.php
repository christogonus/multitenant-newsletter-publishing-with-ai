<?php

use App\Actions\PublishIssue;
use App\Enums\ConnectionStatus;
use App\Enums\Platform;
use App\Enums\Product;
use App\Integrations\Drivers\GhostDriver;
use App\Integrations\Drivers\MailchimpDriver;
use App\Integrations\PlatformException;
use App\Integrations\PlatformManager;
use App\Jobs\PushIssueJob;
use App\Jobs\SyncSubscribersJob;
use App\Livewire\Integrations\Index;
use App\Models\Issue;
use App\Models\IssueDelivery;
use App\Models\Newsletter;
use App\Models\PlatformConnection;
use App\Models\Subscriber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function connectionFor(Platform $platform, array $credentials, ?Newsletter $newsletter = null): PlatformConnection
{
    $newsletter ??= Newsletter::factory()->for(buyer(Product::Oto1Pro))->create();

    return PlatformConnection::create([
        'user_id' => $newsletter->user_id,
        'newsletter_id' => $newsletter->id,
        'platform' => $platform,
        'credentials' => $credentials,
        'status' => ConnectionStatus::Connected,
    ]);
}

beforeEach(fn () => Http::preventStrayRequests());

test('credentials are stored encrypted', function () {
    $connection = connectionFor(Platform::Kit, ['api_key' => 'super-secret-key']);

    $raw = DB::table('platform_connections')->value('credentials');

    expect($raw)->not->toContain('super-secret-key')
        ->and($connection->fresh()->credential('api_key'))->toBe('super-secret-key');
});

test('beehiiv adds readers with double opt-in and pushes the issue as a draft post', function () {
    Http::fake([
        'api.beehiiv.com/v2/publications/pub_123/subscriptions' => Http::response(['data' => ['id' => 'sub_1']], 201),
        'api.beehiiv.com/v2/publications/pub_123/posts' => Http::response(['data' => ['id' => 'post_9']], 201),
    ]);
    $connection = connectionFor(Platform::Beehiiv, ['api_key' => 'bh-key', 'publication_id' => 'pub_123']);
    $subscriber = Subscriber::factory()->for($connection->newsletter)->create();
    $issue = Issue::factory()->for($connection->newsletter)->published()->create();

    $driver = app(PlatformManager::class)->for(Platform::Beehiiv);
    $driver->syncSubscribers($connection, collect([$subscriber]));
    $remoteId = $driver->pushIssue($connection, $issue);

    expect($remoteId)->toBe('post_9');
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/subscriptions')
        && $request['email'] === $subscriber->email
        && $request['double_opt_override'] === 'on'
        && $request->hasHeader('Authorization', 'Bearer bh-key'));
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/posts') && $request['status'] === 'draft');
});

test('ghost signs requests with a short-lived admin token', function () {
    $token = GhostDriver::token('abc123:'.bin2hex('secret'), 1_700_000_000);
    [$header, $payload, $signature] = explode('.', $token);

    expect(json_decode(base64_decode(strtr($header, '-_', '+/')), true))->toMatchArray(['alg' => 'HS256', 'kid' => 'abc123'])
        ->and(json_decode(base64_decode(strtr($payload, '-_', '+/')), true))->toMatchArray(['iat' => 1_700_000_000, 'exp' => 1_700_000_300, 'aud' => '/admin/']);

    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", 'secret', true)), '+/', '-_'), '=');
    expect($signature)->toBe($expected);
});

test('ghost creates a draft post from html and treats existing members as fine', function () {
    Http::fake([
        'myblog.ghost.io/ghost/api/admin/members/' => Http::response(['errors' => [['message' => 'Member already exists']]], 422),
        'myblog.ghost.io/ghost/api/admin/posts/*' => Http::response(['posts' => [['id' => 'ghost_1']]], 201),
    ]);
    $connection = connectionFor(Platform::Ghost, ['site_url' => 'https://myblog.ghost.io', 'admin_key' => 'id1:'.bin2hex('s3cret')]);
    $issue = Issue::factory()->for($connection->newsletter)->published()->create();
    $driver = app(PlatformManager::class)->for(Platform::Ghost);

    $driver->syncSubscribers($connection, Subscriber::factory()->for($connection->newsletter)->count(1)->create());
    expect($driver->pushIssue($connection, $issue))->toBe('ghost_1');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'posts/?source=html')
        && str_starts_with($request->header('Authorization')[0], 'Ghost ')
        && $request['posts'][0]['status'] === 'draft');
});

test('kit adds readers to the form and creates a draft broadcast', function () {
    Http::fake([
        'api.kit.com/v4/subscribers' => Http::response(['subscriber' => ['id' => 1]], 201),
        'api.kit.com/v4/forms/555/subscribers' => Http::response(['subscriber' => ['id' => 1]], 201),
        'api.kit.com/v4/broadcasts' => Http::response(['broadcast' => ['id' => 777]], 201),
    ]);
    $connection = connectionFor(Platform::Kit, ['api_key' => 'kit-key', 'form_id' => '555']);
    $issue = Issue::factory()->for($connection->newsletter)->published()->create();
    $driver = app(PlatformManager::class)->for(Platform::Kit);

    $driver->syncSubscribers($connection, Subscriber::factory()->for($connection->newsletter)->count(1)->create());

    expect($driver->pushIssue($connection, $issue))->toBe('777');
    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Kit-Api-Key', 'kit-key') && str_ends_with($request->url(), 'forms/555/subscribers'));
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'broadcasts') && $request['send_at'] === null && str_contains($request['content'], 'role="presentation"'));
});

test('mailchimp adds readers as pending so they confirm, and builds a campaign', function () {
    Http::fake([
        'us21.api.mailchimp.com/3.0/lists/aud1/members/*' => Http::response(['id' => 'm1'], 200),
        'us21.api.mailchimp.com/3.0/campaigns' => Http::response(['id' => 'camp1'], 200),
        'us21.api.mailchimp.com/3.0/campaigns/camp1/content' => Http::response([], 200),
    ]);
    $connection = connectionFor(Platform::Mailchimp, ['api_key' => 'abc-us21', 'audience_id' => 'aud1', 'from_name' => 'Pat', 'reply_to' => 'pat@example.com']);
    $subscriber = Subscriber::factory()->for($connection->newsletter)->create(['email' => 'Reader@Example.com']);
    $issue = Issue::factory()->for($connection->newsletter)->published()->create();
    $driver = app(PlatformManager::class)->for(Platform::Mailchimp);

    $driver->syncSubscribers($connection, collect([$subscriber]));
    expect($driver->pushIssue($connection, $issue))->toBe('camp1');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/members/'.md5('reader@example.com')) && $request['status_if_new'] === 'pending');
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'campaigns/camp1/content') && $request->method() === 'PUT');
});

test('a mailchimp key without its data centre gets a plain-English fix', function () {
    expect(fn () => MailchimpDriver::dataCenter('nodatacenter'))->toThrow(PlatformException::class, 'does not look complete');
});

test('mailerlite adds readers as unconfirmed and creates a campaign', function () {
    Http::fake([
        'connect.mailerlite.com/api/subscribers' => Http::response(['data' => ['id' => 's1']], 201),
        'connect.mailerlite.com/api/campaigns' => Http::response(['data' => ['id' => 'c1']], 201),
    ]);
    $connection = connectionFor(Platform::MailerLite, ['api_key' => 'ml-key', 'group_id' => 'g1', 'from_name' => 'Pat', 'from_email' => 'pat@example.com']);
    $driver = app(PlatformManager::class)->for(Platform::MailerLite);

    $driver->syncSubscribers($connection, Subscriber::factory()->for($connection->newsletter)->count(1)->create());
    expect($driver->pushIssue($connection, Issue::factory()->for($connection->newsletter)->published()->create()))->toBe('c1');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'api/subscribers') && $request['status'] === 'unconfirmed' && $request['groups'] === ['g1']);
});

test('provider errors turn into plain-English fixes', function (int $status, string $expected, bool $retryable) {
    Http::fake(['api.kit.com/*' => Http::response(['message' => 'nope'], $status)]);
    $connection = connectionFor(Platform::Kit, ['api_key' => 'bad']);

    try {
        app(PlatformManager::class)->for(Platform::Kit)->test($connection);
        $this->fail('Expected a PlatformException');
    } catch (PlatformException $exception) {
        expect($exception->getMessage())->toContain($expected)
            ->and($exception->fix)->not->toBeEmpty()
            ->and($exception->retryable)->toBe($retryable);
    }
})->with([
    [401, 'did not accept your key', false],
    [403, 'plan does not allow', false],
    [404, 'could not find', false],
    [429, 'slow down', true],
    [503, 'having problems', true],
]);

test('the sync job copies new readers and records when it ran', function () {
    Http::fake(['api.kit.com/v4/subscribers' => Http::response(['subscriber' => ['id' => 1]], 201)]);
    $connection = connectionFor(Platform::Kit, ['api_key' => 'kit-key']);
    Subscriber::factory()->for($connection->newsletter)->count(3)->create();
    Subscriber::factory()->for($connection->newsletter)->create(['unsubscribed_at' => now()]);

    SyncSubscribersJob::dispatchSync($connection->newsletter);

    Http::assertSentCount(3);
    expect($connection->fresh()->last_synced_at)->not->toBeNull()
        ->and(Subscriber::whereNotNull('synced_at')->count())->toBe(3);
});

test('a rejected key marks the connection as needing attention', function () {
    Http::fake(['api.kit.com/*' => Http::response([], 401)]);
    $connection = connectionFor(Platform::Kit, ['api_key' => 'revoked']);
    Subscriber::factory()->for($connection->newsletter)->create();

    SyncSubscribersJob::dispatchSync($connection->newsletter);

    $connection->refresh();
    expect($connection->status)->toBe(ConnectionStatus::Error)->and($connection->last_error)->toContain('Copy the key');
});

test('the push job records the delivery and does not push twice', function () {
    Http::fake(['api.kit.com/v4/broadcasts' => Http::response(['broadcast' => ['id' => 42]], 201)]);
    $connection = connectionFor(Platform::Kit, ['api_key' => 'kit-key']);
    $issue = Issue::factory()->for($connection->newsletter)->published()->create();

    PushIssueJob::dispatchSync($issue, $connection);
    PushIssueJob::dispatchSync($issue, $connection);

    Http::assertSentCount(1);
    expect(IssueDelivery::sole())->status->toBe('sent')->remote_id->toBe('42');
});

test('pro auto-publish pushes to connected platforms when an issue is published', function () {
    Queue::fake();
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto1Pro))->create(['auto_publish' => true]);
    connectionFor(Platform::Kit, ['api_key' => 'k'], $newsletter);

    app(PublishIssue::class)->handle(Issue::factory()->for($newsletter)->create());

    Queue::assertPushed(PushIssueJob::class);
});

test('lite buyers can connect one service and are asked to upgrade for a second', function () {
    Http::fake(['api.kit.com/v4/account' => Http::response(['account' => []], 200)]);
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto1Lite))->create();
    $this->actingAs($newsletter->user);

    Livewire::test(Index::class)
        ->call('edit', 'kit')
        ->set('credentials.api_key', 'kit-key')
        ->call('connect')
        ->assertHasNoErrors();

    expect(PlatformConnection::count())->toBe(1);

    Livewire::test(Index::class)
        ->call('edit', 'beehiiv')
        ->set('credentials.api_key', 'bh')
        ->set('credentials.publication_id', 'pub_1')
        ->call('connect')
        ->assertDispatched('toast', tone: 'warning');

    expect(PlatformConnection::count())->toBe(1);
});

test('a failed test connection shows the fix and saves nothing', function () {
    Http::fake(['api.kit.com/*' => Http::response([], 401)]);
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto1Lite))->create();
    $this->actingAs($newsletter->user);

    Livewire::test(Index::class)
        ->call('edit', 'kit')
        ->set('credentials.api_key', 'wrong')
        ->call('connect')
        ->assertHasErrors('connection');

    expect(PlatformConnection::count())->toBe(0);
});

test('lite buyers can download an issue as an email file', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto1Lite))->create();
    $issue = Issue::factory()->for($newsletter)->published()->create(['title' => 'Export me']);

    $response = $this->actingAs($newsletter->user)->get(route('issues.export', $issue));

    $response->assertOk()->assertDownload('export-me.html');
    expect($response->streamedContent())->toContain('Export me');
});
