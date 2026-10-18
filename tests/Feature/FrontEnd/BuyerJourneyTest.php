<?php

use App\Actions\StartIssueGeneration;
use App\Ai\Agents\IssueWriter;
use App\Ai\Agents\NicheResearcher;
use App\Enums\AiUsageKind;
use App\Enums\IssueStatus;
use App\Livewire\Issues\Editor;
use App\Livewire\Onboarding\CreatePublication;
use App\Models\AiUsage;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Models\User;
use App\Notifications\WelcomeBuyer;
use App\Support\Entitlements;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function fakeIssueOutput(array $overrides = []): array
{
    return array_merge([
        'title' => 'Five Ways To Save On Groceries',
        'subject_line' => 'Your grocery bill, cut in half',
        'preheader' => 'Simple swaps that add up fast.',
        'intro' => "Food prices keep climbing.\n\nHere is what actually helps.",
        'sections' => [
            ['heading' => 'Plan before you shop', 'body' => 'A list stops impulse buys.', 'bullets' => ['Check the cupboard', 'Plan five meals'], 'link_label' => 'Price guide', 'link_url' => 'https://example.org/prices'],
            ['heading' => 'Buy own-brand', 'body' => 'Store brands are often made in the same factories.', 'bullets' => [], 'link_label' => 'Made up', 'link_url' => 'https://invented.example/fake'],
        ],
        'closing' => 'See you next week.',
    ], $overrides);
}

beforeEach(function () {
    configureLaunchpad();
    config(['newspub.ai.research_enabled' => true]);
    Notification::fake();
});

test('a buyer goes from purchase to a live public issue', function () {
    NicheResearcher::fake(["Grocery prices rose again this month.\nhttps://example.org/prices"]);
    IssueWriter::fake([fakeIssueOutput()]);

    // Purchase arrives and the buyer sets a password from the welcome email.
    postIpn(ipnPayload())->assertOk();
    $user = User::where('email', 'buyer@example.com')->sole();
    $welcomeUrl = WelcomeBuyer::setPasswordUrl($user);
    $this->post($welcomeUrl, ['password' => 'a-strong-password-1', 'password_confirmation' => 'a-strong-password-1', 'terms' => '1'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertOk()->assertSee('Start My Newsletter');

    // Pick a niche, name it, and the first issue is written.
    Livewire::test(CreatePublication::class)
        ->call('chooseNiche', 'personal-finance')
        ->assertSet('step', 2)
        ->set('name', 'Money Made Simple')
        ->set('subdomain', 'money-made-simple')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $newsletter = Newsletter::sole();
    $issue = Issue::sole();

    expect($newsletter->subdomain)->toBe('money-made-simple')
        ->and($issue->status)->toBe(IssueStatus::Draft)
        ->and($issue->title)->toBe('Five Ways To Save On Groceries')
        ->and(collect($issue->blocks)->pluck('type')->unique()->values()->all())->toContain('heading', 'paragraph', 'bullets', 'link');

    // Only the researched link survives; the invented one is dropped.
    $links = collect($issue->blocks)->where('type', 'link')->pluck('url')->all();
    expect($links)->toBe(['https://example.org/prices']);

    expect(AiUsage::where('kind', AiUsageKind::Issue)->count())->toBe(1)
        ->and(AiUsage::where('kind', AiUsageKind::Research)->count())->toBe(1);

    // Review in the editor and publish.
    Livewire::test(Editor::class, ['issue' => $issue])
        ->set('title', 'Five Ways To Save On Groceries This Week')
        ->call('publish')
        ->assertRedirect(route('issues.share', $issue));

    $issue->refresh();
    expect($issue->status)->toBe(IssueStatus::Published)->and($issue->public_slug)->toBe('five-ways-to-save-on-groceries-this-week');

    // The share screen and the public page show it.
    $this->get(route('issues.share', $issue))->assertOk()->assertSee($issue->publicUrl());

    $this->get(publicationUrl($newsletter))->assertOk()->assertSee('Money Made Simple')->assertSee('Five Ways To Save On Groceries This Week');
    $this->get(publicationUrl($newsletter, 'issues/'.$issue->public_slug))->assertOk()->assertSee('Plan before you shop')->assertSee('Subscribe');
});

test('research is cached per niche so many publications share one search', function () {
    NicheResearcher::fake(['Notes https://example.org/a']);
    IssueWriter::fake([fakeIssueOutput(), fakeIssueOutput()]);

    $first = Newsletter::factory()->create(['niche' => 'Gardening']);
    $second = Newsletter::factory()->create(['niche' => 'gardening ']);

    app(StartIssueGeneration::class)->handle($first);
    app(StartIssueGeneration::class)->handle($second);

    NicheResearcher::assertPromptedTimes(1);
    IssueWriter::assertPromptedTimes(2);
});

test('a failed generation can be retried and does not count toward the weekly quota', function () {
    config(['newspub.ai.research_enabled' => false]);
    IssueWriter::fake(fn () => throw new RuntimeException('provider down'));

    $newsletter = Newsletter::factory()->create();

    try {
        app(StartIssueGeneration::class)->handle($newsletter);
    } catch (RuntimeException) {
        // The sync queue rethrows after the job's failed() handler runs.
    }

    $issue = Issue::sole();
    expect($issue->status)->toBe(IssueStatus::Failed)
        ->and(app(Entitlements::class)->issuesUsedThisWeek($newsletter->user))->toBe(0);

    IssueWriter::fake([fakeIssueOutput()]);

    $this->actingAs($newsletter->user);
    Livewire::test(Editor::class, ['issue' => $issue])->call('retry');

    expect($issue->fresh()->status)->toBe(IssueStatus::Draft);
});
