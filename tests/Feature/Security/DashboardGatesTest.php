<?php

use App\Ai\AiUsageRecorder;
use App\Enums\AiUsageKind;
use App\Jobs\GenerateIssueJob;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;

test('only admins may open the Horizon and Pulse dashboards', function (string $ability) {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create();

    expect(Gate::forUser($admin)->allows($ability))->toBeTrue()
        ->and(Gate::forUser($buyer)->allows($ability))->toBeFalse();
})->with(['viewHorizon', 'viewPulse']);

test('the queue retry window is longer than the slowest job', function () {
    $generationTimeout = (new GenerateIssueJob(new Issue))->timeout;

    expect(config('horizon.defaults.generation.timeout'))->toBeGreaterThan($generationTimeout)
        ->and(config('queue.connections.redis.retry_after'))->toBeGreaterThan(config('horizon.defaults.generation.timeout'))
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan($generationTimeout);
});

test('ai usage is recorded for the pulse spend card', function () {
    $response = new AgentResponse('inv-1', 'text', new TextUsage(1_000_000, 0), new Meta('anthropic', 'model'));

    $usage = app(AiUsageRecorder::class)->record($response, AiUsageKind::Issue, Newsletter::factory()->create());

    expect((float) $usage->cost)->toBe((float) config('newspub.ai.price_per_million_input'));
});
