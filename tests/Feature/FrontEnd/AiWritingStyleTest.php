<?php

use App\Actions\StartIssueGeneration;
use App\Ai\Agents\IssueWriter;
use App\Ai\Agents\NicheResearcher;
use App\Ai\Agents\SocialRepurposer;
use App\Enums\SocialChannel;
use App\Models\Issue;
use App\Models\Newsletter;
use Laravel\Ai\Enums\Lab;

beforeEach(function () {
    config(['newspub.ai.research_enabled' => false, 'newspub.ai.reasoning_effort' => 'low']);
});

test('em dashes, curly quotes and ellipsis characters never reach a written issue', function () {
    IssueWriter::fake([[
        'title' => 'Prices rose — and fast',
        'subject_line' => '“Cheap” food is back…',
        'preheader' => 'Open 9–5 every day',
        'intro' => "— Food prices keep climbing.\n\nHere’s what helps — honestly.",
        'sections' => [
            ['heading' => 'Plan first', 'body' => 'A list stops impulse buys —.', 'bullets' => ['Check the cupboard – twice'], 'link_label' => '', 'link_url' => ''],
        ],
        'closing' => 'See you next week.',
    ]]);

    app(StartIssueGeneration::class)->handle(Newsletter::factory()->create());

    $issue = Issue::sole();
    $text = json_encode([$issue->title, $issue->subject_line, $issue->preheader, $issue->blocks], JSON_UNESCAPED_UNICODE);

    expect($issue->title)->toBe('Prices rose, and fast')
        ->and($issue->subject_line)->toBe('"Cheap" food is back...')
        ->and($issue->preheader)->toBe('Open 9-5 every day')
        ->and($issue->blocks[0]['text'])->toBe('Food prices keep climbing.')
        ->and($issue->blocks[1]['text'])->toBe("Here's what helps, honestly.")
        ->and($issue->blocks[3]['text'])->toBe('A list stops impulse buys.')
        ->and($issue->blocks[4]['items'])->toBe(['Check the cupboard, twice'])
        ->and($text)->not->toMatch('/[—–“”’…]/u');
});

test('every writer is told the house style', function () {
    $newsletter = Newsletter::factory()->create();

    expect((string) (new IssueWriter($newsletter))->instructions())->toContain('Never use em dashes')
        ->and((string) (new SocialRepurposer(SocialChannel::LinkedIn))->instructions())->toContain('Avoid AI filler words');
});

test('reasoning effort is only sent to OpenAI reasoning models', function (string $providers, ?string $model, Lab $provider, array $expected) {
    config(['newspub.ai.provider' => $providers, 'newspub.ai.model' => $model, 'ai.providers.openai.models.text.default' => 'gpt-4.1']);

    expect((new NicheResearcher)->providerOptions($provider))->toBe($expected);
})->with([
    'gpt-5 model' => ['openai', 'gpt-5-mini', Lab::OpenAI, ['reasoning' => ['effort' => 'low']]],
    'o-series model' => ['openai', 'o4-mini', Lab::OpenAI, ['reasoning' => ['effort' => 'low']]],
    'non-reasoning model' => ['openai', 'gpt-4.1-mini', Lab::OpenAI, []],
    'gpt-5 chat model' => ['openai', 'gpt-5-chat-latest', Lab::OpenAI, []],
    'Anthropic' => ['anthropic', 'claude-sonnet-5-5', Lab::Anthropic, []],
    'OpenAI fail-over uses its own default model' => ['anthropic,openai', 'claude-sonnet-5-5', Lab::OpenAI, []],
]);

test('reasoning effort can be switched off', function () {
    config(['newspub.ai.provider' => 'openai', 'newspub.ai.model' => 'gpt-5', 'newspub.ai.reasoning_effort' => '']);

    expect((new NicheResearcher)->providerOptions(Lab::OpenAI))->toBe([]);
});
