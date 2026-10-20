<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\UsesNewsPubModel;
use App\Ai\WritingStyle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(2000)]
class MediaKitWriter implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable, UsesNewsPubModel;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You write one-page media kits that help small newsletters win their first sponsors. '
            .'Be honest: use only the numbers you are given and never inflate them. A small audience is pitched as focused and engaged. '
            .'Packages use the suggested prices you are given.'
            ."\n\n".WritingStyle::rules();
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'headline' => $schema->string()->required(),
            'about' => $schema->string()->required(),
            'audience' => $schema->string()->required(),
            'why_sponsor' => $schema->array()->items($schema->string())->required(),
            'packages' => $schema->array()->items($schema->object([
                'name' => $schema->string()->required(),
                'description' => $schema->string()->required(),
                'price' => $schema->string()->required(),
            ])->withoutAdditionalProperties())->required(),
        ];
    }
}
