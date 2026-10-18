<?php

namespace App\Ai;

use App\Ai\Contracts\NewsletterWriter;
use App\Ai\Data\IssueDraft;
use App\Enums\SocialChannel;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Support\Blocks;

/**
 * A deterministic writer for browser tests and demos without an AI key. Enabled with NEWSPUB_AI_FAKE=true.
 * It never calls a provider, so it records no ai_usage rows.
 */
class FakeNewsletterWriter implements NewsletterWriter
{
    public function research(Newsletter $newsletter): ?string
    {
        return null;
    }

    public function writeIssue(Newsletter $newsletter, ?string $topic = null, ?string $research = null): IssueDraft
    {
        $title = $topic ?: 'Three simple wins in '.$newsletter->niche;

        return new IssueDraft(
            title: $title,
            subjectLine: $title,
            preheader: 'A quick, practical read for this week.',
            blocks: Blocks::normalize([
                ['type' => 'paragraph', 'text' => 'Welcome to this week\'s issue of '.$newsletter->name.'.'],
                ['type' => 'heading', 'text' => 'Start small'],
                ['type' => 'paragraph', 'text' => 'Pick one idea from this issue and try it today.'],
                ['type' => 'bullets', 'items' => ['Write it down', 'Do it once', 'Notice what changed']],
                ['type' => 'heading', 'text' => 'Keep going'],
                ['type' => 'paragraph', 'text' => 'Small steps add up. See you next time.'],
            ]),
        );
    }

    public function suggestTopics(Newsletter $newsletter, int $count = 10): array
    {
        return array_map(fn (int $number) => ucfirst($newsletter->niche).' idea '.$number, range(1, $count));
    }

    public function repurpose(Issue $issue, SocialChannel $channel): string
    {
        return $channel->label().' for "'.$issue->title.'": '.$issue->publicUrl();
    }

    public function mediaKit(Newsletter $newsletter, array $stats): array
    {
        return [
            'headline' => 'Reach '.$stats['subscribers'].' readers of '.$newsletter->name,
            'about' => 'A newsletter about '.$newsletter->niche.'.',
            'audience' => $newsletter->audience ?: 'Curious readers',
            'why_sponsor' => ['Focused audience', 'Readers who act on recommendations'],
            'packages' => [['name' => 'Main sponsor', 'description' => 'Top of one issue', 'price' => '$25']],
        ];
    }

    public function leadPage(Newsletter $newsletter, ?string $idea = null): array
    {
        return [
            'headline' => 'Get the free '.$newsletter->niche.' starter guide',
            'subheadline' => 'Join '.$newsletter->name.' and get it straight away.',
            'bullets' => ['Simple steps', 'No jargon'],
            'button_label' => 'Send it to me',
            'magnet_title' => ucfirst($newsletter->niche).' starter guide',
            'magnet_sections' => [['heading' => 'Step one', 'body' => 'Start here.']],
        ];
    }
}
