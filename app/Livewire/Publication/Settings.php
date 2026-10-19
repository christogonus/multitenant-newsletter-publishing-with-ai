<?php

namespace App\Livewire\Publication;

use App\Actions\CreateNewsletter;
use App\Enums\Frequency;
use App\Enums\Product;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Support\PublicPageCache;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Publication settings')]
class Settings extends Component
{
    use InteractsWithPublication;

    public string $name = '';

    public string $tagline = '';

    public string $niche = '';

    public string $audience = '';

    public string $voice = '';

    public string $frequency = '';

    public string $subdomain = '';

    public bool $autoPublish = false;

    public string $confirmName = '';

    public function mount(): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $this->name = $newsletter->name;
        $this->tagline = (string) $newsletter->tagline;
        $this->niche = $newsletter->niche;
        $this->audience = (string) $newsletter->audience;
        $this->voice = (string) $newsletter->voice;
        $this->frequency = $newsletter->frequency->value;
        $this->subdomain = $newsletter->subdomain;
        $this->autoPublish = $newsletter->auto_publish;
    }

    public function save(): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'niche' => ['required', 'string', 'max:80'],
            'audience' => ['nullable', 'string', 'max:160'],
            'voice' => ['nullable', 'string', 'max:300'],
            'frequency' => ['required', Rule::enum(Frequency::class)],
            'subdomain' => CreateNewsletter::subdomainRules($newsletter),
        ], [
            'subdomain.unique' => 'That web address is taken. Try adding a word.',
            'subdomain.regex' => 'Use only lowercase letters, numbers and dashes.',
        ]);

        $newsletter->update([
            ...$validated,
            'tagline' => $validated['tagline'] ?: null,
            'audience' => $validated['audience'] ?: null,
            'voice' => $validated['voice'] ?: null,
            'auto_publish' => $this->autoPublish && $this->entitlements()->canAutoPublish($this->user()),
        ]);

        PublicPageCache::bust($newsletter);
        unset($this->newsletter);
        $this->toast('Settings saved.');
    }

    public function deletePublication(): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('delete', $newsletter);

        $this->validate(['confirmName' => ['required', Rule::in([$newsletter->name])]], [
            'confirmName.in' => 'Type the name exactly to confirm.',
        ]);

        $newsletter->delete();
        $this->user()->forceFill(['current_newsletter_id' => null])->save();

        $this->redirectRoute('dashboard');
    }

    public function render(): View
    {
        return view('livewire.publication.settings', [
            'frequencies' => collect(Frequency::cases())->mapWithKeys(fn (Frequency $frequency) => [$frequency->value => $frequency->label()])->all(),
            'canAutoPublish' => $this->entitlements()->canAutoPublish($this->user()),
            'publicRoot' => config('newspub.public_root'),
            'deliveryProduct' => Product::Oto1Delivery,
        ]);
    }
}
