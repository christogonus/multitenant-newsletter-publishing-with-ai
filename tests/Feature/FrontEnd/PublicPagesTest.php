<?php

use App\Actions\PublishIssue;
use App\Enums\Product;
use App\Models\Domain;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Models\Report;
use App\Models\Subscriber;
use App\Support\Blocks;
use Illuminate\Support\Facades\URL;

test('only published issues of visible publications are shown publicly', function () {
    $newsletter = Newsletter::factory()->create();
    $live = Issue::factory()->for($newsletter)->published()->create(['title' => 'Live issue']);
    $draft = Issue::factory()->for($newsletter)->create(['title' => 'Secret draft', 'public_slug' => 'secret-draft']);

    $this->get(publicationUrl($newsletter))->assertOk()->assertSee('Live issue')->assertDontSee('Secret draft');
    $this->get(publicationUrl($newsletter, 'issues/'.$live->public_slug))->assertOk();
    $this->get(publicationUrl($newsletter, 'issues/secret-draft'))->assertNotFound();
});

test('an unknown subdomain is a 404', function () {
    $this->get('https://nobody.'.config('newspub.public_root').'/')->assertNotFound();
});

test('a moderated publication is offline', function () {
    $newsletter = Newsletter::factory()->create(['moderated_at' => now()]);

    $this->get(publicationUrl($newsletter))->assertStatus(410)->assertSee('unavailable');
});

test('the preview path on the app host serves the same page', function () {
    $newsletter = Newsletter::factory()->create(['name' => 'Preview Weekly']);

    $this->get(appUrl('p/'.$newsletter->subdomain))->assertOk()->assertSee('Preview Weekly');
});

test('a verified custom domain serves the publication and an unverified one does not', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto8Premium))->create(['name' => 'Custom Weekly']);
    Domain::create(['newsletter_id' => $newsletter->id, 'hostname' => 'news.example.com', 'verification_token' => 'abc', 'verified_at' => now()]);
    Domain::create(['newsletter_id' => Newsletter::factory()->create()->id, 'hostname' => 'pending.example.com', 'verification_token' => 'def']);

    $this->get('https://news.example.com/')->assertOk()->assertSee('Custom Weekly');
    $this->get('https://pending.example.com/')->assertNotFound();
});

test('issue content is escaped and unsafe links never render', function () {
    $newsletter = Newsletter::factory()->create();
    $issue = Issue::factory()->for($newsletter)->published()->create([
        'blocks' => Blocks::normalize([
            ['type' => 'paragraph', 'text' => '<script>alert(1)</script>Hello'],
            ['type' => 'link', 'text' => 'Click', 'url' => 'javascript:alert(1)'],
            ['type' => 'image', 'url' => 'data:image/png;base64,AAA', 'alt' => 'x'],
        ]),
    ]);

    $this->get(publicationUrl($newsletter, 'issues/'.$issue->public_slug))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('javascript:alert', false)
        ->assertDontSee('data:image', false)
        ->assertSee('Hello');
});

test('a reader can subscribe and their consent is recorded', function () {
    $newsletter = Newsletter::factory()->create();

    $this->post(publicationUrl($newsletter, 'subscribe'), ['email' => 'Reader@Example.com'], ['REMOTE_ADDR' => '203.0.113.9'])
        ->assertRedirect(publicationUrl($newsletter, 'thanks'));

    $subscriber = Subscriber::sole();
    expect($subscriber->email)->toBe('reader@example.com')
        ->and($subscriber->consent_at)->not->toBeNull()
        ->and($subscriber->consent_ip)->toBe('203.0.113.9')
        ->and($subscriber->source)->toBe('hosted_page');

    $this->get(publicationUrl($newsletter, 'thanks'))->assertOk()->assertSee('You are on the list');
});

test('subscribing twice does not create a duplicate', function () {
    $newsletter = Newsletter::factory()->create();

    $this->post(publicationUrl($newsletter, 'subscribe'), ['email' => 'reader@example.com']);
    $this->post(publicationUrl($newsletter, 'subscribe'), ['email' => 'reader@example.com']);

    expect(Subscriber::count())->toBe(1);
});

test('a bot that fills the honeypot is shown thanks but not stored', function () {
    $newsletter = Newsletter::factory()->create();

    $this->post(publicationUrl($newsletter, 'subscribe'), ['email' => 'bot@example.com', 'website' => 'http://spam.example'])
        ->assertRedirect(publicationUrl($newsletter, 'thanks'));

    expect(Subscriber::count())->toBe(0);
});

test('a bot that submits instantly is ignored', function () {
    $newsletter = Newsletter::factory()->create();

    $this->post(publicationUrl($newsletter, 'subscribe'), ['email' => 'fast@example.com', 'started_at' => encrypt(now()->timestamp)]);

    expect(Subscriber::count())->toBe(0);
});

test('the subscribe form is rate limited', function () {
    $newsletter = Newsletter::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(publicationUrl($newsletter, 'subscribe'), ['email' => "reader{$attempt}@example.com"])->assertRedirect();
    }

    $this->post(publicationUrl($newsletter, 'subscribe'), ['email' => 'reader6@example.com'])->assertTooManyRequests();
    expect(Subscriber::count())->toBe(5);
});

test('an invalid email shows a plain error', function () {
    $newsletter = Newsletter::factory()->create();

    $this->from(publicationUrl($newsletter))
        ->post(publicationUrl($newsletter, 'subscribe'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});

test('anyone can report a page', function () {
    $newsletter = Newsletter::factory()->create();
    $issue = Issue::factory()->for($newsletter)->published()->create();

    $this->post(publicationUrl($newsletter, 'report'), ['reason' => 'spam', 'details' => 'Looks like a scam', 'issue' => $issue->id])->assertRedirect();

    $report = Report::sole();
    expect($report->newsletter_id)->toBe($newsletter->id)->and($report->issue_id)->toBe($issue->id);
});

test('a report cannot point at another publication\'s issue', function () {
    $newsletter = Newsletter::factory()->create();
    $otherIssue = Issue::factory()->published()->create();

    $this->post(publicationUrl($newsletter, 'report'), ['reason' => 'spam', 'issue' => $otherIssue->id]);

    expect(Report::sole()->issue_id)->toBeNull();
});

test('a reader can unsubscribe and delete their details with a signed link', function () {
    $subscriber = Subscriber::factory()->create();
    $show = URL::signedRoute('readers.unsubscribe', ['subscriber' => $subscriber->id]);

    $this->get($show)->assertOk();
    $this->post($show)->assertRedirect();
    expect($subscriber->fresh()->unsubscribed_at)->not->toBeNull();

    $this->post(URL::signedRoute('readers.delete', ['subscriber' => $subscriber->id]))->assertOk();
    expect(Subscriber::count())->toBe(0);
});

test('publication pages never carry the app icon or logo', function () {
    $newsletter = Newsletter::factory()->create(['name' => 'Garden Weekly']);
    $subscriber = Subscriber::factory()->for($newsletter)->create();

    $pages = [
        $this->get(publicationUrl($newsletter)),
        $this->get(URL::signedRoute('readers.unsubscribe', ['subscriber' => $subscriber->id])),
        $this->post(URL::signedRoute('readers.delete', ['subscriber' => $subscriber->id])),
    ];

    foreach ($pages as $page) {
        $page->assertOk()->assertSee('Garden Weekly')->assertDontSee('icon-square.webp')->assertDontSee('icon-touch.png')->assertDontSee('logo-dark');
    }
});

test('unsigned reader links are rejected', function () {
    $subscriber = Subscriber::factory()->create();

    $this->get(appUrl('readers/'.$subscriber->id.'/unsubscribe'))->assertForbidden();
});

test('public pages are cached and publishing busts the cache', function () {
    $newsletter = Newsletter::factory()->create();
    $this->get(publicationUrl($newsletter))->assertDontSee('Fresh issue');

    $issue = Issue::factory()->for($newsletter)->create(['title' => 'Fresh issue']);
    $this->get(publicationUrl($newsletter))->assertDontSee('Fresh issue');

    app(PublishIssue::class)->handle($issue);
    $this->get(publicationUrl($newsletter))->assertSee('Fresh issue');
});
