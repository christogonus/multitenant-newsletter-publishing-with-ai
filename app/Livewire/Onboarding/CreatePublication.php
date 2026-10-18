<?php

namespace App\Livewire\Onboarding;

use App\Actions\CreateNewsletter;
use App\Actions\StartIssueGeneration;
use App\Enums\Frequency;
use App\Exceptions\PublicationLimitReachedException;
use App\Exceptions\QuotaExceededException;
use App\Livewire\Concerns\InteractsWithPublication;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Start your newsletter')]
class CreatePublication extends Component
{
    use InteractsWithPublication;

    public int $step = 1;

    public string $search = '';

    public string $niche = '';

    public string $customNiche = '';

    public string $name = '';

    public string $audience = '';

    public string $frequency = 'weekly';

    public string $subdomain = '';

    public bool $subdomainEdited = false;

    public function mount(): void
    {
        if (! $this->entitlements()->canCreatePublication($this->user())) {
            $this->redirectRoute($this->user()->newsletters()->exists() ? 'publications.index' : 'dashboard', navigate: false);
        }
    }

    /**
     * @return list<array<string, string>>
     */
    #[Computed]
    public function niches(): array
    {
        $niches = config('niches');

        if ($this->search === '') {
            return $niches;
        }

        $term = Str::lower($this->search);

        return array_values(array_filter($niches, fn (array $niche) => str_contains(Str::lower($niche['label'].' '.$niche['description']), $term)));
    }

    public function chooseNiche(string $key): void
    {
        $niche = collect(config('niches'))->firstWhere('key', $key);

        if (! $niche) {
            return;
        }

        $this->niche = $niche['label'];
        $this->customNiche = '';
        $this->name = $this->name ?: $niche['name'];
        $this->audience = $this->audience ?: $niche['audience'];
        $this->syncSubdomain();
        $this->step = 2;
    }

    public function useCustomNiche(): void
    {
        $this->validate(['customNiche' => ['required', 'string', 'min:3', 'max:80']], [
            'customNiche.required' => 'Type a topic, for example "vegan baking" or "golf tips".',
        ]);

        $this->niche = trim($this->customNiche);
        $this->name = $this->name ?: Str::title($this->niche).' Weekly';
        $this->syncSubdomain();
        $this->step = 2;
    }

    public function updatedName(): void
    {
        $this->syncSubdomain();
    }

    public function updatedSubdomain(): void
    {
        $this->subdomainEdited = true;
        $this->subdomain = Str::slug($this->subdomain);
    }

    public function back(): void
    {
        $this->step = 1;
    }

    public function create(CreateNewsletter $createNewsletter, StartIssueGeneration $startIssueGeneration): void
    {
        abort_unless($this->entitlements()->canCreatePublication($this->user()), 403);

        $validated = $this->validate([
            'niche' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'audience' => ['nullable', 'string', 'max:160'],
            'frequency' => ['required', Rule::enum(Frequency::class)],
            'subdomain' => CreateNewsletter::subdomainRules(),
        ], [
            'subdomain.unique' => 'That web address is taken. Try adding a word.',
            'subdomain.regex' => 'Use only letters, numbers and dashes.',
        ]);

        try {
            $newsletter = $createNewsletter->handle($this->user(), $validated);
        } catch (PublicationLimitReachedException) {
            $this->redirectRoute('publications.index');

            return;
        }

        try {
            $issue = $startIssueGeneration->handle($newsletter);
            $this->redirectRoute('issues.edit', $issue);
        } catch (QuotaExceededException) {
            $this->redirectRoute('dashboard');
        }
    }

    public function render(): View
    {
        return view('livewire.onboarding.create-publication', [
            'frequencies' => collect(Frequency::cases())->mapWithKeys(fn (Frequency $frequency) => [$frequency->value => $frequency->label()])->all(),
            'publicRoot' => config('newspub.public_root'),
        ]);
    }

    private function syncSubdomain(): void
    {
        if (! $this->subdomainEdited) {
            $this->subdomain = Str::limit(Str::slug($this->name), 40, '');
        }
    }
}
