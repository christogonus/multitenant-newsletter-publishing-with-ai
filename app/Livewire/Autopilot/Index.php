<?php

namespace App\Livewire\Autopilot;

use App\Actions\StartIssueGeneration;
use App\Ai\Contracts\NewsletterWriter;
use App\Exceptions\QuotaExceededException;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\CalendarItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Title('Autopilot')]
class Index extends Component
{
    use InteractsWithPublication;

    public string $newTopic = '';

    public string $newDate = '';

    public string $publishTime = '09:00';

    public function mount(): void
    {
        $this->newDate = now()->addDay()->format('Y-m-d');
    }

    /**
     * Fill the next 30 days with AI-suggested topics at the publication's frequency.
     */
    public function planMonth(NewsletterWriter $writer): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $dates = $this->upcomingDates(30);
        $taken = $newsletter->calendarItems()->where('scheduled_for', '>=', now())->pluck('scheduled_for')->map(fn ($date) => $date->toDateString());
        $dates = $dates->reject(fn (Carbon $date) => $taken->contains($date->toDateString()))->values();

        if ($dates->isEmpty()) {
            $this->toast('The next 30 days are already planned.', 'info');

            return;
        }

        try {
            $topics = $writer->suggestTopics($newsletter, $dates->count());
        } catch (Throwable $exception) {
            report($exception);
            $this->toast('We could not plan topics right now. Please try again.', 'danger');

            return;
        }

        DB::transaction(function () use ($newsletter, $dates, $topics) {
            foreach ($dates->take(count($topics)) as $index => $date) {
                $newsletter->calendarItems()->create(['topic' => $topics[$index], 'scheduled_for' => $date]);
            }
        });

        $this->toast(count($topics).' topics planned. Press "Write them all" when you are ready.');
    }

    public function addItem(): void
    {
        $this->authorize('update', $this->newsletter);

        $this->validate([
            'newTopic' => ['required', 'string', 'max:160'],
            'newDate' => ['required', 'date', 'after_or_equal:today'],
            'publishTime' => ['required', 'date_format:H:i'],
        ]);

        $this->newsletter->calendarItems()->create([
            'topic' => $this->newTopic,
            'scheduled_for' => Carbon::parse($this->newDate.' '.$this->publishTime),
        ]);

        $this->reset('newTopic');
        $this->toast('Added to your calendar.');
    }

    public function removeItem(int $itemId): void
    {
        $item = $this->ownedItem($itemId);
        $this->authorize('delete', $item);
        $item->delete();
    }

    /**
     * Write every planned issue that has not been written yet, as far as the weekly quota allows.
     */
    public function writeAll(StartIssueGeneration $startIssueGeneration): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $items = $newsletter->calendarItems()->whereNull('issue_id')->where('scheduled_for', '>=', now())->orderBy('scheduled_for')
            ->limit((int) config('newspub.limits.bulk_generation_max'))->get();

        $started = 0;

        foreach ($items as $item) {
            try {
                $issue = $startIssueGeneration->handle($newsletter, $item->topic, scheduledFor: $item->scheduled_for);
            } catch (QuotaExceededException $exception) {
                $this->toast($started > 0 ? "Started {$started} issues. ".$exception->getMessage() : $exception->getMessage(), 'warning');

                return;
            }

            $item->update(['issue_id' => $issue->id]);
            $started++;
        }

        $this->toast($started > 0 ? "Writing {$started} issues. Each one goes live on its date. You can review them any time." : 'Nothing left to write.');
    }

    public function render(): View
    {
        return view('livewire.autopilot.index', [
            'items' => $this->newsletter->calendarItems()
                ->with('issue:id,title,status,scheduled_for')
                ->where('scheduled_for', '>=', now()->startOfDay())
                ->orderBy('scheduled_for')
                ->get()
                ->groupBy(fn (CalendarItem $item) => $item->scheduled_for->format('Y-m-d')),
            'remaining' => $this->entitlements()->remainingIssuesThisWeek($this->user()),
            'unlimited' => $this->entitlements()->hasUnlimitedIssues($this->user()),
        ]);
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function upcomingDates(int $days): Collection
    {
        $interval = $this->newsletter->frequency->intervalDays();
        [$hour, $minute] = array_map('intval', explode(':', $this->publishTime ?: '09:00'));
        $dates = collect();
        $cursor = now()->addDay()->setTime($hour, $minute);
        $end = now()->addDays($days);
        $step = 0;

        while ($cursor->lte($end)) {
            $dates->push($cursor->copy());
            $step++;
            $cursor = now()->addDay()->setTime($hour, $minute)->addDays((int) round($step * $interval));
        }

        return $dates;
    }

    private function ownedItem(int $itemId): CalendarItem
    {
        return $this->newsletter->calendarItems()->findOrFail($itemId);
    }
}
