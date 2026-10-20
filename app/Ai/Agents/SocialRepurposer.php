<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\UsesNewsPubModel;
use App\Ai\WritingStyle;
use App\Enums\SocialChannel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(2000)]
class SocialRepurposer implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable, UsesNewsPubModel;

    public function __construct(public SocialChannel $channel) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $format = match ($this->channel) {
            SocialChannel::XThread => 'An X (Twitter) thread of 5 to 8 posts, each under 270 characters, separated by a blank line and numbered like 1/, 2/. The first post is a strong hook. The last post invites people to subscribe with the link given.',
            SocialChannel::LinkedIn => 'One LinkedIn post of 150 to 250 words: a one-line hook, short paragraphs, 3 takeaways, and a closing line that invites readers to subscribe with the link given. At most 3 hashtags at the end.',
            SocialChannel::VideoScript => 'A 45 to 60 second vertical video script with sections labelled HOOK, POINTS and CALL TO ACTION. Lines are short and spoken, with simple on-screen text suggestions in [brackets].',
        };

        return 'You turn newsletter issues into social content that sounds human and specific. Never invent facts that are not in the issue. Format: '.$format
            ."\n\n".WritingStyle::rules();
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'body' => $schema->string()->required(),
        ];
    }
}
