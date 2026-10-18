<?php

use App\Ai\Agents\IssueWriter;
use App\Ai\Agents\SocialRepurposer;
use App\Ai\Agents\TopicPlanner;
use App\Enums\IssueStatus;
use App\Enums\Product;
use App\Livewire\Autopilot\Index as Autopilot;
use App\Livewire\Design\Index as Design;
use App\Livewire\Issues\Editor;
use App\Livewire\Social\Index as Social;
use App\Models\CalendarItem;
use App\Models\Domain;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Models\SocialPost;
use App\Support\DomainVerifier;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('social buyers turn an issue into posts for each channel', function () {
    SocialRepurposer::fake([['body' => '1/ Hook'], ['body' => 'LinkedIn post'], ['body' => 'HOOK: watch this']]);
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto6Social))->create();
    $issue = Issue::factory()->for($newsletter)->published()->create();
    $this->actingAs($newsletter->user);

    Livewire::test(Social::class, ['issue' => $issue->id])->call('generateAll')->assertSee('1/ Hook');

    expect(SocialPost::count())->toBe(3)
        ->and(SocialPost::pluck('channel')->map->value->sort()->values()->all())->toBe(['linkedin', 'video_script', 'x_thread']);
    SocialRepurposer::assertPrompted(fn ($prompt) => $prompt->contains($issue->publicUrl()));
});

test('social cannot generate posts for another buyer\'s issue', function () {
    SocialRepurposer::fake();
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto6Social))->create();
    $foreign = Issue::factory()->published()->create();
    $this->actingAs($newsletter->user);

    Livewire::test(Social::class, ['issue' => $foreign->id])->assertSet('issue', null);
    SocialRepurposer::assertNeverPrompted();
});

test('autopilot plans a month at the publication frequency and writes the issues on schedule', function () {
    TopicPlanner::fake(fn () => ['topics' => ['Topic A', 'Topic B', 'Topic C', 'Topic D', 'Topic E']]);
    IssueWriter::fake();
    config(['newspub.ai.research_enabled' => false]);

    $newsletter = Newsletter::factory()->for(buyer(Product::Oto7Autopilot))->create(['frequency' => 'weekly']);
    $this->actingAs($newsletter->user);

    $component = Livewire::test(Autopilot::class)->call('planMonth');

    $items = CalendarItem::orderBy('scheduled_for')->get();
    expect($items)->toHaveCount(5)
        ->and($items->first()->topic)->toBe('Topic A')
        ->and((int) round($items[0]->scheduled_for->diffInDays($items[1]->scheduled_for)))->toBe(7);

    $component->call('writeAll');

    expect(Issue::count())->toBe(5)
        ->and(Issue::where('status', IssueStatus::Scheduled)->count())->toBe(5)
        ->and(CalendarItem::whereNull('issue_id')->count())->toBe(0);
});

test('autopilot stops writing at the weekly quota and says so', function () {
    IssueWriter::fake();
    config(['newspub.ai.research_enabled' => false, 'newspub.limits.weekly_issues' => 2]);
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto7Autopilot))->create();
    foreach (range(1, 4) as $day) {
        $newsletter->calendarItems()->create(['topic' => "Topic {$day}", 'scheduled_for' => now()->addDays($day)]);
    }
    $this->actingAs($newsletter->user);

    Livewire::test(Autopilot::class)->call('writeAll')->assertDispatched('toast', tone: 'warning');

    expect(Issue::count())->toBe(2);
});

test('scheduled issues go live when they are due', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto7Autopilot))->create();
    $due = Issue::factory()->for($newsletter)->create(['status' => IssueStatus::Scheduled, 'scheduled_for' => now()->subMinute()]);
    $later = Issue::factory()->for($newsletter)->create(['status' => IssueStatus::Scheduled, 'scheduled_for' => now()->addDay()]);

    $this->artisan('newspub:publish-scheduled')->assertSuccessful();

    expect($due->fresh()->status)->toBe(IssueStatus::Published)
        ->and($due->fresh()->public_slug)->not->toBeNull()
        ->and($later->fresh()->status)->toBe(IssueStatus::Scheduled);
});

test('scheduled issues of a refunded autopilot buyer fall back to drafts', function () {
    $issue = Issue::factory()->create(['status' => IssueStatus::Scheduled, 'scheduled_for' => now()->subMinute()]);

    $this->artisan('newspub:publish-scheduled');

    expect($issue->fresh()->status)->toBe(IssueStatus::Draft);
});

test('the editor schedules an issue for autopilot buyers only', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto7Autopilot))->create();
    $issue = Issue::factory()->for($newsletter)->create();
    $this->actingAs($newsletter->user);

    Livewire::test(Editor::class, ['issue' => $issue])
        ->set('scheduleAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('schedule')
        ->assertHasNoErrors();

    expect($issue->fresh()->status)->toBe(IssueStatus::Scheduled);

    $basic = Issue::factory()->create();
    $this->actingAs($basic->newsletter->user);
    Livewire::test(Editor::class, ['issue' => $basic])->set('scheduleAt', now()->addDay()->format('Y-m-d\TH:i'))->call('schedule')->assertForbidden();
});

test('premium templates and branding need the premium upgrade', function () {
    $newsletter = Newsletter::factory()->create();
    $this->actingAs($newsletter->user);

    Livewire::test(Design::class)->call('chooseTemplate', 'midnight')->assertDispatched('toast', tone: 'warning');
    expect($newsletter->fresh()->template)->toBe('classic');

    Livewire::test(Design::class)->call('chooseTemplate', 'bold');
    expect($newsletter->fresh()->template)->toBe('bold');

    Livewire::test(Design::class)->call('saveBranding')->assertForbidden();
});

test('premium buyers set brand colours that reach the public page', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto8Premium))->create();
    $this->actingAs($newsletter->user);

    Livewire::test(Design::class)->call('chooseTemplate', 'midnight')->set('accentColor', '#ff6600')->set('font', 'georgia')->call('saveBranding')->assertHasNoErrors();

    $this->get(publicationUrl($newsletter))->assertOk()->assertSee('--accent: #ff6600', false);
});

test('a brand colour that is not a hex colour is rejected', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto8Premium))->create();
    $this->actingAs($newsletter->user);

    Livewire::test(Design::class)->set('accentColor', 'red;}</style><script>')->call('saveBranding')->assertHasErrors('accentColor');
});

test('custom domains are verified by their record and then allowed by the proxy check', function () {
    Http::fake();
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto8Premium))->create();
    $this->actingAs($newsletter->user);

    Livewire::test(Design::class)->set('hostname', 'https://News.Example.com/')->call('saveDomain')->assertHasNoErrors();
    $domain = Domain::sole();
    expect($domain->hostname)->toBe('news.example.com');

    $this->get(appUrl('internal/domains/allowed?domain=news.example.com'))->assertNotFound();

    $this->mock(DomainVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->once()->andReturnUsing(function (Domain $domain) {
        $domain->update(['verified_at' => now()]);

        return true;
    }));

    Livewire::test(Design::class)->call('verifyDomain')->assertDispatched('toast', tone: 'success');

    $this->get(appUrl('internal/domains/allowed?domain=news.example.com'))->assertOk();
    expect($newsletter->fresh()->publicUrl())->toBe('https://news.example.com');
});

test('the proxy check allows publication subdomains and refuses unknown hosts', function () {
    $newsletter = Newsletter::factory()->create();

    $this->get(appUrl('internal/domains/allowed?domain='.$newsletter->subdomain.'.'.config('newspub.public_root')))->assertOk();
    $this->get(appUrl('internal/domains/allowed?domain=nobody.'.config('newspub.public_root')))->assertNotFound();
    $this->get(appUrl('internal/domains/allowed?domain=evil.example'))->assertNotFound();
    $this->get(appUrl('internal/domains/allowed'))->assertStatus(400);
});

test('the domain verifier reads the txt record', function () {
    $domain = Domain::create(['newsletter_id' => Newsletter::factory()->create()->id, 'hostname' => 'news.example.com', 'verification_token' => 'tok']);

    $verifier = new class extends DomainVerifier
    {
        protected function txtRecords(string $name): array
        {
            return $name === '_newspub.news.example.com' ? ['newspub-verify=tok'] : [];
        }
    };

    expect($verifier->verify($domain))->toBeTrue()->and($domain->fresh()->verified_at)->not->toBeNull();
});
