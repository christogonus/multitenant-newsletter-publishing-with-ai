<?php

namespace App\Models;

use App\Enums\GenerationStage;
use App\Enums\IssueStatus;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'newsletter_id', 'title', 'topic', 'subject_line', 'preheader', 'blocks', 'status', 'is_ai_generated', 'generation_stage',
    'generation_error', 'public_slug', 'scheduled_for', 'published_at', 'is_paid_only',
])]
class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_ai_generated' => 'boolean',
            'is_paid_only' => 'boolean',
            'status' => IssueStatus::class,
            'generation_stage' => GenerationStage::class,
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'views' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Newsletter, $this>
     */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    /**
     * @return HasMany<SocialPost, $this>
     */
    public function socialPosts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    /**
     * @return HasMany<IssueDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(IssueDelivery::class);
    }

    /**
     * The only query public routes use: published issues of visible publications.
     *
     * @param  Builder<Issue>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('status', IssueStatus::Published)
            ->whereNotNull('published_at')
            ->whereHas('newsletter', fn (Builder $newsletters) => $newsletters->visible());
    }

    public function isPublished(): bool
    {
        return $this->status === IssueStatus::Published;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [IssueStatus::Draft, IssueStatus::Scheduled, IssueStatus::Published], true);
    }

    public function publicUrl(): string
    {
        return $this->newsletter->publicUrl('issues/'.$this->public_slug);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function blockList(): array
    {
        return array_values($this->blocks ?? []);
    }

    /**
     * Whether readers must be paying to read past the opening: the issue is marked for paying readers
     * and the newsletter has paid subscriptions turned on.
     */
    public function isLocked(): bool
    {
        return $this->is_paid_only && $this->newsletter->hasPaidSubscriptions();
    }

    /**
     * The free opening of a locked issue: every block up to and including the first paragraph.
     *
     * @return list<array<string, mixed>>
     */
    public function teaserBlocks(): array
    {
        $teaser = [];

        foreach ($this->blockList() as $block) {
            $teaser[] = $block;

            if (($block['type'] ?? null) === 'paragraph') {
                return $teaser;
            }
        }

        // No paragraph to stop at, so show nothing rather than the whole issue.
        return [];
    }

    /**
     * The blocks a reader without paid access may see.
     *
     * @return list<array<string, mixed>>
     */
    public function publicBlocks(): array
    {
        return $this->isLocked() ? $this->teaserBlocks() : $this->blockList();
    }

    /**
     * Plain text of every block, used as context for repurposing agents.
     */
    public function plainText(): string
    {
        return collect($this->blockList())
            ->map(fn (array $block) => match ($block['type'] ?? null) {
                'bullets' => collect($block['items'] ?? [])->map(fn ($item) => '- '.$item)->implode("\n"),
                'link' => trim(($block['text'] ?? '').' '.($block['url'] ?? '')),
                'image', 'divider' => null,
                default => $block['text'] ?? null,
            })
            ->filter()
            ->implode("\n\n");
    }
}
