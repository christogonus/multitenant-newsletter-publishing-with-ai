<?php

namespace App\Ai;

use App\Ai\Agents\IssueWriter;
use App\Ai\Agents\LeadPageWriter;
use App\Ai\Agents\MediaKitWriter;
use App\Ai\Agents\NicheResearcher;
use App\Ai\Agents\SocialRepurposer;
use App\Ai\Agents\TopicPlanner;
use App\Ai\Contracts\NewsletterWriter;
use App\Ai\Data\IssueDraft;
use App\Enums\AiUsageKind;
use App\Enums\SocialChannel;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Support\Blocks;
use App\Support\RateGuide;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The laravel/ai implementation of the writer. The only class that talks to the SDK's agents.
 */
class LaravelAiNewsletterWriter implements NewsletterWriter
{
    public function __construct(private AiUsageRecorder $usage) {}

    public function research(Newsletter $newsletter): ?string
    {
        if (! config('newspub.ai.research_enabled')) {
            return null;
        }

        $key = 'research:'.md5(Str::lower(trim($newsletter->niche)));

        try {
            return Cache::remember($key, now()->addHours((int) config('newspub.ai.research_cache_hours')), function () use ($newsletter) {
                $response = (new NicheResearcher)->prompt('Niche: '.$newsletter->niche.'. Today is '.now()->toFormattedDateString().'.');
                $this->usage->record($response, AiUsageKind::Research, $newsletter);

                return trim($response->text) ?: null;
            });
        } catch (Throwable $exception) {
            Log::warning('Niche research failed; writing without fresh stories.', ['niche' => $newsletter->niche, 'error' => $exception->getMessage()]);

            return null;
        }
    }

    public function writeIssue(Newsletter $newsletter, ?string $topic = null, ?string $research = null): IssueDraft
    {
        $prompt = collect([
            $topic ? "Write this issue about: {$topic}." : 'Pick the most useful topic for this week and write the issue.',
            $research ? "Research notes (the only source of links you may use):\n{$research}" : 'No research notes are available, so leave all link fields empty.',
            $this->recentTitles($newsletter),
        ])->filter()->implode("\n\n");

        $response = (new IssueWriter($newsletter))->prompt($prompt);
        $this->usage->record($response, AiUsageKind::Issue, $newsletter);

        $allowedUrls = $research ? $this->urlsIn($research) : [];
        $output = WritingStyle::cleanAll($response->toArray());

        return new IssueDraft(
            title: Str::limit(trim((string) ($output['title'] ?? '')) ?: ($topic ?: $newsletter->name), 180, ''),
            subjectLine: Str::limit(trim((string) ($output['subject_line'] ?? '')), 150, ''),
            preheader: Str::limit(trim((string) ($output['preheader'] ?? '')), 200, ''),
            blocks: $this->toBlocks($output, $allowedUrls),
        );
    }

    public function suggestTopics(Newsletter $newsletter, int $count = 10): array
    {
        $covered = $newsletter->issues()->latest()->limit(30)->pluck('title')->implode('; ');

        $response = (new TopicPlanner($newsletter))->prompt(
            "Suggest {$count} topics.".($covered ? " Already covered: {$covered}" : '')
        );
        $this->usage->record($response, AiUsageKind::Topics, $newsletter);

        return collect($response['topics'] ?? [])
            ->map(fn ($topic) => Str::limit(trim(WritingStyle::clean((string) $topic)), 120, ''))
            ->filter()
            ->unique()
            ->take($count)
            ->values()
            ->all();
    }

    public function repurpose(Issue $issue, SocialChannel $channel): string
    {
        $response = (new SocialRepurposer($channel))->prompt(implode("\n\n", [
            'Issue title: '.$issue->title,
            $issue->plainText(),
            'Link to the issue: '.$issue->publicUrl(),
        ]));
        $this->usage->record($response, AiUsageKind::Social, $issue->newsletter);

        return trim(WritingStyle::clean((string) ($response['body'] ?? '')));
    }

    public function mediaKit(Newsletter $newsletter, array $stats): array
    {
        $response = (new MediaKitWriter)->prompt(implode("\n", [
            "Newsletter: {$newsletter->name}",
            "Niche: {$newsletter->niche}",
            'Audience: '.($newsletter->audience ?: 'not specified'),
            'Frequency: '.$newsletter->frequency->label(),
            "Active readers: {$stats['subscribers']}",
            "Issues published: {$stats['issues']}",
            'Average web views per issue: '.($stats['average_views'] ?? 'not tracked yet'),
            'Suggested prices: '.collect(app(RateGuide::class)->packages($stats['subscribers']))->map(fn ($package) => $package['name'].' '.$package['price'])->implode(', '),
        ]));
        $this->usage->record($response, AiUsageKind::MediaKit, $newsletter);
        $response = WritingStyle::cleanAll($response->toArray());

        return [
            'headline' => (string) ($response['headline'] ?? ''),
            'about' => (string) ($response['about'] ?? ''),
            'audience' => (string) ($response['audience'] ?? ''),
            'why_sponsor' => array_values(array_map('strval', $response['why_sponsor'] ?? [])),
            'packages' => array_values(array_map(fn ($package) => [
                'name' => (string) ($package['name'] ?? ''),
                'description' => (string) ($package['description'] ?? ''),
                'price' => (string) ($package['price'] ?? ''),
            ], $response['packages'] ?? [])),
        ];
    }

    public function leadPage(Newsletter $newsletter, ?string $idea = null): array
    {
        $response = (new LeadPageWriter)->prompt(implode("\n", array_filter([
            "Newsletter: {$newsletter->name} ({$newsletter->niche})",
            $newsletter->audience ? "Audience: {$newsletter->audience}" : null,
            $idea ? "Gift idea from the owner: {$idea}" : 'Choose the most useful free gift for this audience.',
        ])));
        $this->usage->record($response, AiUsageKind::LeadPage, $newsletter);
        $response = WritingStyle::cleanAll($response->toArray());

        return [
            'headline' => (string) ($response['headline'] ?? ''),
            'subheadline' => (string) ($response['subheadline'] ?? ''),
            'bullets' => array_values(array_map('strval', $response['bullets'] ?? [])),
            'button_label' => (string) ($response['button_label'] ?? 'Send it to me'),
            'magnet_title' => (string) ($response['magnet_title'] ?? ''),
            'magnet_sections' => array_values(array_map(fn ($section) => [
                'heading' => (string) ($section['heading'] ?? ''),
                'body' => (string) ($section['body'] ?? ''),
            ], $response['magnet_sections'] ?? [])),
        ];
    }

    /**
     * Turn the writer's structured sections into stored blocks. Links not found in the research are dropped.
     *
     * @param  array<string, mixed>  $output
     * @param  list<string>  $allowedUrls
     * @return list<array<string, mixed>>
     */
    private function toBlocks(array $output, array $allowedUrls): array
    {
        $blocks = [];

        foreach ($this->paragraphs($output['intro'] ?? '') as $paragraph) {
            $blocks[] = ['type' => 'paragraph', 'text' => $paragraph];
        }

        foreach ((array) ($output['sections'] ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }

            $blocks[] = ['type' => 'heading', 'text' => (string) ($section['heading'] ?? '')];

            foreach ($this->paragraphs($section['body'] ?? '') as $paragraph) {
                $blocks[] = ['type' => 'paragraph', 'text' => $paragraph];
            }

            $bullets = array_values(array_filter(array_map('trim', array_map('strval', (array) ($section['bullets'] ?? [])))));

            if ($bullets !== []) {
                $blocks[] = ['type' => 'bullets', 'items' => $bullets];
            }

            $url = Blocks::safeUrl($section['link_url'] ?? '');

            if ($url !== '' && in_array(rtrim($url, '/'), $allowedUrls, true)) {
                $blocks[] = ['type' => 'link', 'text' => (string) ($section['link_label'] ?: 'Read more'), 'url' => $url, 'description' => ''];
            }
        }

        foreach ($this->paragraphs($output['closing'] ?? '') as $paragraph) {
            $blocks[] = ['type' => 'paragraph', 'text' => $paragraph];
        }

        return Blocks::normalize(array_values(array_filter(
            $blocks,
            fn (array $block) => ($block['type'] !== 'heading' && $block['type'] !== 'paragraph') || trim($block['text']) !== '',
        )));
    }

    /**
     * @return list<string>
     */
    private function paragraphs(mixed $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', (string) $text) ?: [])));
    }

    /**
     * @return list<string>
     */
    private function urlsIn(string $text): array
    {
        preg_match_all('#https?://[^\s<>"\')\]]+#i', $text, $matches);

        return array_values(array_unique(array_map(fn ($url) => rtrim($url, '/.,;'), $matches[0])));
    }

    private function recentTitles(Newsletter $newsletter): ?string
    {
        $titles = $newsletter->issues()->whereNotNull('published_at')->latest('published_at')->limit(10)->pluck('title');

        return $titles->isEmpty() ? null : 'Recent issues (do not repeat them): '.$titles->implode('; ');
    }
}
