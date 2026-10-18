<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\UsesNewsPubModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(3000)]
class LeadPageWriter implements Agent, HasStructuredOutput
{
    use Promptable, UsesNewsPubModel;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You write high-converting but honest newsletter sign-up pages and the free gift (lead magnet) readers get for joining. '
            .'The gift is a short, genuinely useful guide or checklist of 4 to 6 sections. Plain English, no hype, no fake numbers or testimonials.';
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'headline' => $schema->string()->required(),
            'subheadline' => $schema->string()->required(),
            'bullets' => $schema->array()->items($schema->string())->required(),
            'button_label' => $schema->string()->required(),
            'magnet_title' => $schema->string()->required(),
            'magnet_sections' => $schema->array()->items($schema->object([
                'heading' => $schema->string()->required(),
                'body' => $schema->string()->required(),
            ])->withoutAdditionalProperties())->required(),
        ];
    }
}
