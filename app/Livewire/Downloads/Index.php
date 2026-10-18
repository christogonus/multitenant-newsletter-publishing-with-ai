<?php

namespace App\Livewire\Downloads;

use App\Models\BumpOffer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The buyer's order bump files: PDFs to download and image packs to preview or download as one zip.
 */
#[Title('Downloads')]
class Index extends Component
{
    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.downloads.index', [
            'bumps' => BumpOffer::query()
                ->whereIn('id', $user->purchases()->standing()->whereNotNull('bump_offer_id')->select('bump_offer_id'))
                ->with('files')
                ->latest('id')
                ->get(),
        ]);
    }
}
