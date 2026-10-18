<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\UsesNewsPubModel;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;
use Stringable;

/**
 * Finds fresh stories for a niche with the provider's web search tool. Results are cached per niche.
 */
#[MaxTokens(1500)]
class NicheResearcher implements Agent, HasTools
{
    use Promptable, UsesNewsPubModel;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You are a research assistant for newsletter writers.',
            'Search the web for the most useful and recent developments in the niche you are given (last 14 days where possible).',
            'Return 5 to 8 short notes. Each note: one sentence on what happened and why it matters, then the source URL on its own line.',
            'Only include facts you found in your search results. Never invent sources.',
        ]);
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [(new WebSearch)->max(4)];
    }
}
