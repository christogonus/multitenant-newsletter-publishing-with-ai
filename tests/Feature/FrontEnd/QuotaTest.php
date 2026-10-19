<?php

use App\Actions\StartIssueGeneration;
use App\Ai\Agents\IssueWriter;
use App\Enums\IssueStatus;
use App\Enums\Product;
use App\Exceptions\QuotaExceededException;
use App\Jobs\GenerateIssueJob;
use App\Livewire\Issues\Create;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Support\Entitlements;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    config(['newspub.ai.research_enabled' => false]);
    IssueWriter::fake();
});

test('the front end allows 15 AI issues per rolling week and blocks the 16th', function () {
    $newsletter = Newsletter::factory()->create();
    Issue::factory()->count(15)->for($newsletter)->create();

    expect(fn () => app(StartIssueGeneration::class)->handle($newsletter))->toThrow(QuotaExceededException::class);
    expect(Issue::count())->toBe(15);
});

test('the quota frees up as issues age past seven days', function () {
    $newsletter = Newsletter::factory()->create();
    Issue::factory()->count(15)->for($newsletter)->create(['created_at' => now()->subDays(8)]);

    app(StartIssueGeneration::class)->handle($newsletter);

    expect(Issue::count())->toBe(16);
});

test('kit seed issues and failed attempts do not use the quota', function () {
    $newsletter = Newsletter::factory()->create();
    Issue::factory()->count(10)->for($newsletter)->create(['is_ai_generated' => false]);
    Issue::factory()->count(10)->for($newsletter)->create(['status' => IssueStatus::Failed]);

    expect(app(Entitlements::class)->remainingIssuesThisWeek($newsletter->user, $newsletter))->toBe(15);
});

test('each newsletter has its own 15 issues a week', function () {
    $user = buyer();
    $first = Newsletter::factory()->for($user)->create();
    $second = Newsletter::factory()->for($user)->create();
    Issue::factory()->count(15)->for($first)->create();

    expect(fn () => app(StartIssueGeneration::class)->handle($first))->toThrow(QuotaExceededException::class);

    app(StartIssueGeneration::class)->handle($second);
    expect($second->issues()->count())->toBe(1);
});

test('unlimited creation lifts the weekly cap up to the hidden fair-use ceiling', function () {
    config(['newspub.limits.fair_use_weekly_issues' => 20]);
    $user = buyer(Product::Oto2Silver);
    $newsletter = Newsletter::factory()->for($user)->create();
    Issue::factory()->count(19)->for($newsletter)->create();

    app(StartIssueGeneration::class)->handle($newsletter);
    expect(Issue::count())->toBe(20);

    expect(fn () => app(StartIssueGeneration::class)->handle($newsletter))->toThrow(QuotaExceededException::class, 'fair-use');
});

test('gold buyers use the priority queue lane and silver buyers do not', function () {
    Queue::fake();
    $gold = Newsletter::factory()->for(buyer(Product::Oto2Gold))->create();
    $silver = Newsletter::factory()->for(buyer(Product::Oto2Silver))->create();

    app(StartIssueGeneration::class)->handle($gold);
    app(StartIssueGeneration::class)->handle($silver);

    Queue::assertPushedOn('generation-priority', GenerateIssueJob::class, fn (GenerateIssueJob $job) => $job->issue->newsletter_id === $gold->id);
    Queue::assertPushedOn('generation', GenerateIssueJob::class, fn (GenerateIssueJob $job) => $job->issue->newsletter_id === $silver->id);
});

test('gold buyers\' issues are written with the premium model when one is set', function () {
    config(['newspub.ai.model' => 'standard-model', 'newspub.ai.premium_model' => 'premium-model']);

    expect((new IssueWriter(Newsletter::factory()->for(buyer(Product::Oto2Gold))->create()))->model())->toBe('premium-model')
        ->and((new IssueWriter(Newsletter::factory()->for(buyer(Product::Oto2Silver))->create()))->model())->toBe('standard-model');

    config(['newspub.ai.premium_model' => null]);
    expect((new IssueWriter(Newsletter::factory()->for(buyer(Product::Oto2Gold))->create()))->model())->toBe('standard-model');
});

test('the write screen shows a plain message when the week is used up', function () {
    $newsletter = Newsletter::factory()->create();
    Issue::factory()->count(15)->for($newsletter)->create();

    $this->actingAs($newsletter->user);

    Livewire::test(Create::class)
        ->call('generate')
        ->assertHasErrors('topic')
        ->assertSee('used all 15 issues');
});
