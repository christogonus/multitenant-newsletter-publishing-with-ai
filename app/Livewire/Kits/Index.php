<?php

namespace App\Livewire\Kits;

use App\Actions\InstallKit;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\Kit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ready-made kits')]
class Index extends Component
{
    use InteractsWithPublication;

    public string $search = '';

    public function install(int $kitId, InstallKit $installKit): void
    {
        $kit = Kit::query()->findOrFail($kitId);

        if (! $this->entitlements()->canCreatePublication($this->user())) {
            $this->toast('You have reached your publication limit. Upgrade to the Multi-Newsletter Manager for unlimited publications.', 'warning');

            return;
        }

        $newsletter = $installKit->handle($this->user(), $kit);

        $this->redirectRoute('dashboard');
        session()->flash('toast', ['message' => $newsletter->name.' is installed with '.count($kit->seed_issues).' ready issues.', 'tone' => 'success']);
    }

    public function render(): View
    {
        $kits = Kit::query()
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('niche', 'like', '%'.$this->search.'%')))
            ->orderBy('name')
            ->get();

        return view('livewire.kits.index', [
            'kits' => $kits,
            'installed' => $this->user()->newsletters()->whereNotNull('kit_key')->pluck('kit_key')->all(),
            'limit' => $this->entitlements()->publicationLimit($this->user()),
            'used' => $this->user()->newsletters()->count(),
            'rightsSlug' => Str::slug('commercial rights'),
        ]);
    }
}
