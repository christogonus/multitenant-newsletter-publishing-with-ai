<?php

namespace App\Livewire\Vault;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The order bump: gated content pages with ranked niches, subject-line swipes and a 30-day plan.
 */
#[Title('Niche and Swipe Vault')]
class Index extends Component
{
    public const SECTIONS = ['niches' => 'Ranked niches', 'subject-lines' => 'Subject lines', 'plan' => '30-day plan'];

    public string $section = 'niches';

    public function mount(?string $section = null): void
    {
        $this->section = array_key_exists((string) $section, self::SECTIONS) ? $section : 'niches';
    }

    public function render(): View
    {
        return view('livewire.vault.index', [
            'sections' => self::SECTIONS,
            'vault' => config('vault', ['niches' => [], 'subject_lines' => [], 'plan' => []]),
        ]);
    }
}
