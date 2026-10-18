<?php

namespace App\Livewire\Growth;

use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\LeadPage;
use App\Support\Blocks;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Edit sign-up page')]
class LeadPageEditor extends Component
{
    use InteractsWithPublication;

    public LeadPage $leadPage;

    public string $headline = '';

    public string $subheadline = '';

    public string $bulletsText = '';

    public string $buttonLabel = '';

    public string $magnetTitle = '';

    public string $magnetText = '';

    public bool $isLive = true;

    public function mount(LeadPage $leadPage): void
    {
        $this->authorize('update', $leadPage);
        $this->leadPage = $leadPage;
        $this->headline = $leadPage->headline;
        $this->subheadline = (string) $leadPage->subheadline;
        $this->bulletsText = implode("\n", $leadPage->bullets ?? []);
        $this->buttonLabel = $leadPage->button_label;
        $this->magnetTitle = $leadPage->magnet_title;
        $this->magnetText = collect($leadPage->magnet_blocks ?? [])
            ->map(fn (array $block) => $block['type'] === 'heading' ? '## '.$block['text'] : ($block['text'] ?? ''))
            ->filter()
            ->implode("\n\n");
        $this->isLive = $leadPage->is_live;
    }

    public function save(): void
    {
        $this->authorize('update', $this->leadPage);

        $this->validate([
            'headline' => ['required', 'string', 'max:250'],
            'subheadline' => ['nullable', 'string', 'max:250'],
            'bulletsText' => ['nullable', 'string', 'max:2000'],
            'buttonLabel' => ['required', 'string', 'max:60'],
            'magnetTitle' => ['required', 'string', 'max:250'],
            'magnetText' => ['required', 'string', 'max:30000'],
        ]);

        $blocks = collect(preg_split('/\n\s*\n/', $this->magnetText) ?: [])
            ->map(fn (string $chunk) => str_starts_with(trim($chunk), '## ')
                ? ['type' => 'heading', 'text' => trim(substr(trim($chunk), 3))]
                : ['type' => 'paragraph', 'text' => trim($chunk)])
            ->all();

        $this->leadPage->update([
            'headline' => $this->headline,
            'subheadline' => $this->subheadline ?: null,
            'bullets' => array_values(array_filter(array_map('trim', explode("\n", $this->bulletsText)))),
            'button_label' => $this->buttonLabel,
            'magnet_title' => $this->magnetTitle,
            'magnet_blocks' => Blocks::normalize($blocks),
            'is_live' => $this->isLive,
        ]);

        $this->toast('Sign-up page saved.');
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->leadPage);
        $this->leadPage->delete();
        $this->redirectRoute('growth.index');
    }

    public function render(): View
    {
        return view('livewire.growth.lead-page-editor', [
            'url' => $this->leadPage->publicUrl(),
        ]);
    }
}
