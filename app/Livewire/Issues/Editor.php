<?php

namespace App\Livewire\Issues;

use App\Actions\PublishIssue;
use App\Actions\StartIssueGeneration;
use App\Enums\BlockType;
use App\Enums\IssueStatus;
use App\Enums\Product;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\Issue;
use App\Support\Blocks;
use App\Support\PublicPageCache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Editor extends Component
{
    use InteractsWithPublication, WithFileUploads;

    public Issue $issue;

    public string $title = '';

    public string $subjectLine = '';

    public string $preheader = '';

    public bool $paidOnly = false;

    /** @var list<array<string, mixed>> */
    public array $blocks = [];

    public string $scheduleAt = '';

    public int $previewVersion = 0;

    /**
     * Pictures being uploaded, keyed by the id of the picture block they belong to.
     *
     * @var array<string, TemporaryUploadedFile|null>
     */
    public array $imageUploads = [];

    public function mount(Issue $issue): void
    {
        $this->authorize('update', $issue);
        $this->issue = $issue;
        $this->fillFromIssue();
    }

    /**
     * Polled while the writer is working, so the three-stage panel moves along.
     */
    public function refreshGeneration(): void
    {
        $this->issue->refresh();

        if ($this->issue->status !== IssueStatus::Generating) {
            $this->fillFromIssue();
        }
    }

    public function retry(StartIssueGeneration $startIssueGeneration): void
    {
        $this->authorize('update', $this->issue);

        if ($this->issue->status !== IssueStatus::Failed) {
            return;
        }

        $startIssueGeneration->handle($this->issue->newsletter, $this->issue->topic, retry: $this->issue);
        $this->issue->refresh();
    }

    public function updated(string $property): void
    {
        if (in_array(strtok($property, '.'), ['title', 'subjectLine', 'preheader', 'blocks', 'paidOnly'], true)) {
            $this->save(quiet: true);
        }
    }

    public function save(bool $quiet = false): void
    {
        $this->authorize('update', $this->issue);

        $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'subjectLine' => ['nullable', 'string', 'max:150'],
            'preheader' => ['nullable', 'string', 'max:200'],
        ], [
            'title.required' => 'Give your issue a title.',
        ]);

        $this->blocks = Blocks::normalize($this->blocks);

        $this->issue->update([
            'title' => $this->title,
            'subject_line' => $this->subjectLine ?: null,
            'preheader' => $this->preheader ?: null,
            'blocks' => $this->blocks,
            'is_paid_only' => $this->user()->owns(Product::Oto4Sponsor) ? $this->paidOnly : (bool) $this->issue->is_paid_only,
        ]);

        if ($this->issue->isPublished()) {
            PublicPageCache::bust($this->issue->newsletter);
        }

        $this->previewVersion++;

        if (! $quiet) {
            $this->toast('Saved.');
        }
    }

    public function addBlock(string $type): void
    {
        $blockType = BlockType::tryFrom($type);

        if (! $blockType) {
            return;
        }

        $this->blocks[] = Blocks::blank($blockType);
        $this->save(quiet: true);
    }

    public function removeBlock(string $id): void
    {
        $this->blocks = array_values(array_filter($this->blocks, fn (array $block) => $block['id'] !== $id));
        $this->save(quiet: true);
    }

    public function moveBlock(string $id, int $position): void
    {
        $index = collect($this->blocks)->search(fn (array $block) => $block['id'] === $id);

        if ($index === false) {
            return;
        }

        $block = $this->blocks[$index];
        array_splice($this->blocks, $index, 1);
        array_splice($this->blocks, max(0, min($position, count($this->blocks))), 0, [$block]);
        $this->save(quiet: true);
    }

    /**
     * Stores an uploaded picture and points its block at the public, absolute URL (so it works in emails too).
     */
    public function updatedImageUploads(mixed $value, string $blockId): void
    {
        $this->authorize('update', $this->issue);

        $this->validate(
            ['imageUploads.'.$blockId => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048', 'dimensions:max_width=4000,max_height=4000']],
            ['imageUploads.*.max' => 'Pictures must be smaller than 2 MB.', 'imageUploads.*.image' => 'That file is not a picture.'],
        );

        $index = collect($this->blocks)->search(fn (array $block) => $block['id'] === $blockId && $block['type'] === BlockType::Image->value);

        if ($index === false) {
            unset($this->imageUploads[$blockId]);

            return;
        }

        $path = $this->imageUploads[$blockId]->store('issue-images/'.$this->issue->newsletter_id, 'public');
        unset($this->imageUploads[$blockId]);

        $this->blocks[$index]['url'] = url(Storage::disk('public')->url($path));
        $this->save(quiet: true);
    }

    public function addBullet(int $index): void
    {
        if (isset($this->blocks[$index]) && $this->blocks[$index]['type'] === BlockType::Bullets->value) {
            $this->blocks[$index]['items'][] = '';
        }
    }

    public function removeBullet(int $index, int $itemIndex): void
    {
        if (isset($this->blocks[$index]['items'][$itemIndex])) {
            array_splice($this->blocks[$index]['items'], $itemIndex, 1);
            $this->save(quiet: true);
        }
    }

    public function publish(PublishIssue $publishIssue): void
    {
        $this->save(quiet: true);

        if ($this->blocks === []) {
            $this->toast('Add some content before you publish.', 'warning');

            return;
        }

        $publishIssue->handle($this->issue);
        $this->redirectRoute('issues.share', $this->issue);
    }

    public function unpublish(PublishIssue $publishIssue): void
    {
        $this->authorize('update', $this->issue);
        $publishIssue->unpublish($this->issue);
        $this->issue->refresh();
        $this->toast('Your issue is now a draft and hidden from your page.');
    }

    public function schedule(): void
    {
        $this->authorize('update', $this->issue);
        abort_unless($this->user()->owns(Product::Oto7Autopilot), 403);

        $this->validate(['scheduleAt' => ['required', 'date', 'after:now']], [
            'scheduleAt.after' => 'Pick a time in the future.',
            'scheduleAt.required' => 'Pick a date and time.',
        ]);

        $this->save(quiet: true);
        $this->issue->update([
            'status' => IssueStatus::Scheduled,
            'scheduled_for' => Carbon::parse($this->scheduleAt),
        ]);
        $this->dispatch('close-modal', 'schedule');
        $this->toast('Scheduled for '.$this->issue->scheduled_for->format('D j M, g:ia').'.');
    }

    /**
     * Copies this issue into a new draft. Copies are not AI-written, so they never use the weekly quota.
     */
    public function duplicate(): void
    {
        $this->authorize('update', $this->issue);
        $this->save(quiet: true);

        $copy = $this->issue->newsletter->issues()->create([
            'title' => Str::limit('Copy of '.$this->issue->title, 180, ''),
            'topic' => $this->issue->topic,
            'subject_line' => $this->issue->subject_line,
            'preheader' => $this->issue->preheader,
            'blocks' => Blocks::normalize(array_map(fn (array $block) => [...$block, 'id' => null], $this->blocks)),
            'status' => IssueStatus::Draft,
            'is_ai_generated' => false,
        ]);

        $this->redirectRoute('issues.edit', $copy);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->issue);
        $this->issue->delete();
        PublicPageCache::bust($this->newsletter);
        $this->redirectRoute('issues.index');
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.issues.editor', [
            'blockTypes' => BlockType::cases(),
            'canSchedule' => $user->owns(Product::Oto7Autopilot),
            'canExport' => $user->owns(Product::Oto1Delivery),
            'canRepurpose' => $user->owns(Product::Oto6Social),
            'canLock' => $user->owns(Product::Oto4Sponsor),
        ])->title($this->issue->title);
    }

    private function fillFromIssue(): void
    {
        $this->title = $this->issue->title;
        $this->subjectLine = (string) $this->issue->subject_line;
        $this->preheader = (string) $this->issue->preheader;
        $this->paidOnly = (bool) $this->issue->is_paid_only;
        $this->blocks = Blocks::normalize($this->issue->blocks ?? []);
        $this->scheduleAt = $this->issue->scheduled_for?->format('Y-m-d\TH:i') ?? '';
        $this->previewVersion++;
    }
}
