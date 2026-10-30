<?php

namespace App\Livewire\Admin;

use App\Models\Newsletter;
use App\Models\ReservedSubdomain;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Admin: subdomains no publication may claim. The built-in list lives in config; admins add and remove the rest here.
 * Reserving a name never takes it from a publication that already has it.
 */
#[Title('Admin · Reserved addresses')]
class Subdomains extends Component
{
    public string $name = '';

    public function add(): void
    {
        $this->authorizeAdmin();
        $this->name = Str::lower(trim($this->name));

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:40',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(ReservedSubdomain::names()),
            ],
        ], [
            'name.regex' => 'Use only letters, numbers and dashes.',
            'name.not_in' => 'That address is already reserved.',
        ]);

        ReservedSubdomain::query()->create($validated);
        $this->reset('name');

        $message = Newsletter::query()->where('subdomain', $validated['name'])->exists()
            ? "Reserved. A publication already uses {$validated['name']} and keeps it."
            : "Reserved. New publications can no longer use {$validated['name']}.";

        $this->dispatch('toast', message: $message, tone: 'success');
    }

    public function remove(int $reservedSubdomainId): void
    {
        $this->authorizeAdmin();
        ReservedSubdomain::query()->findOrFail($reservedSubdomainId)->delete();
        $this->dispatch('toast', message: 'Removed. Publications can use that address again.', tone: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.subdomains', [
            'builtIn' => config('newspub.reserved_subdomains'),
            'added' => ReservedSubdomain::query()->orderBy('name')->get(),
            'publicRoot' => config('newspub.public_root'),
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }
}
