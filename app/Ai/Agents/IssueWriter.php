<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\UsesNewsPubModel;
use App\Models\Newsletter;
use App\Support\Entitlements;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(4096)]
#[Temperature(0.7)]
class IssueWriter implements Agent, HasStructuredOutput
{
    use Promptable, UsesNewsPubModel;

    public function __construct(public Newsletter $newsletter) {}

    /**
     * OTO 2 Gold buyers' issues use the premium writing model when one is configured.
     */
    public function model(): ?string
    {
        return app(Entitlements::class)->writingModel($this->newsletter->user);
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $newsletter = $this->newsletter;

        return implode("\n", [
            "You write \"{$newsletter->name}\", an email newsletter about {$newsletter->niche}.",
            $newsletter->audience ? "Readers: {$newsletter->audience}." : 'Readers are curious people who are not experts.',
            'Voice: '.($newsletter->voice ?: 'friendly, clear and confident, like a smart friend explaining what matters.'),
            'It goes out '.strtolower($newsletter->frequency->label()).'.',
            '',
            'Rules:',
            '- Write in plain English with short paragraphs. No jargon, no hype, no emojis in headings.',
            '- 3 to 5 sections. Each section has a heading, one or two short paragraphs, and optional bullet points.',
            '- Never invent statistics, quotes, dates or events. If you are unsure, write about ideas and practical advice instead of news.',
            '- Only use a link_url that appears in the research notes you are given. Otherwise leave link_label and link_url as empty strings.',
            '- subject_line: under 60 characters, specific and curiosity-driven, no clickbait.',
            '- preheader: under 110 characters, complements the subject line.',
            '- Use empty strings or empty lists for anything you do not need.',
        ]);
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'subject_line' => $schema->string()->required(),
            'preheader' => $schema->string()->required(),
            'intro' => $schema->string()->required(),
            'sections' => $schema->array()->items($schema->object([
                'heading' => $schema->string()->required(),
                'body' => $schema->string()->required(),
                'bullets' => $schema->array()->items($schema->string())->required(),
                'link_label' => $schema->string()->required(),
                'link_url' => $schema->string()->required(),
            ])->withoutAdditionalProperties())->required(),
            'closing' => $schema->string()->required(),
        ];
    }
}
