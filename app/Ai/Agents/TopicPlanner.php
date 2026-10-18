<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\UsesNewsPubModel;
use App\Models\Newsletter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(1500)]
class TopicPlanner implements Agent, HasStructuredOutput
{
    use Promptable, UsesNewsPubModel;

    public function __construct(public Newsletter $newsletter) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return "You plan issues for \"{$this->newsletter->name}\", a newsletter about {$this->newsletter->niche}. "
            .'Suggest specific, evergreen-friendly issue topics a non-expert reader would want to open. '
            .'Each topic is a short phrase under 80 characters. Do not repeat topics you are told were already covered.';
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'topics' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
