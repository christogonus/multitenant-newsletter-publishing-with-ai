<?php

namespace App\Livewire\Agency;

use App\Enums\ContractStatus;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Agency clients')]
class Index extends Component
{
    use InteractsWithPublication;

    public ?int $editingId = null;

    public string $name = '';

    public string $contactEmail = '';

    public string $package = 'starter';

    public string $monthlyFee = '';

    public string $contractStatus = 'draft';

    public string $notes = '';

    /** @var list<int> */
    public array $newsletterIds = [];

    public ?int $contractClientId = null;

    public string $agencyName = '';

    public string $startDate = '';

    public int $noticeDays = 30;

    public function mount(): void
    {
        $this->agencyName = $this->user()->name;
        $this->startDate = now()->toDateString();
        $this->usePackage('starter');
    }

    public function usePackage(string $key): void
    {
        $package = collect(config('agency.packages', []))->firstWhere('key', $key);

        if ($package) {
            $this->package = $key;
            $this->monthlyFee = (string) $package['monthly_fee'];
        }
    }

    public function updatedPackage(string $value): void
    {
        $this->usePackage($value);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->dispatch('open-slide-over', 'client-form');
    }

    public function edit(int $clientId): void
    {
        $client = $this->ownedClient($clientId);
        $this->editingId = $client->id;
        $this->name = $client->name;
        $this->contactEmail = (string) $client->contact_email;
        $this->package = $client->package;
        $this->monthlyFee = (string) $client->monthly_fee;
        $this->contractStatus = $client->contract_status->value;
        $this->notes = (string) $client->notes;
        $this->newsletterIds = $client->newsletters()->pluck('newsletters.id')->all();
        $this->dispatch('open-slide-over', 'client-form');
    }

    public function save(): void
    {
        $ownNewsletterIds = $this->user()->newsletters()->pluck('id')->all();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'package' => ['required', Rule::in(collect(config('agency.packages', []))->pluck('key'))],
            'monthlyFee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'contractStatus' => ['required', Rule::enum(ContractStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'newsletterIds' => ['array'],
            'newsletterIds.*' => ['integer', Rule::in($ownNewsletterIds)],
        ]);

        $client = $this->editingId ? $this->ownedClient($this->editingId) : new Client(['user_id' => $this->user()->id]);

        $client->fill([
            'name' => $validated['name'],
            'contact_email' => $validated['contactEmail'] ?: null,
            'package' => $validated['package'],
            'monthly_fee' => $validated['monthlyFee'],
            'contract_status' => $validated['contractStatus'],
            'notes' => $validated['notes'] ?: null,
        ])->save();

        $client->newsletters()->sync($validated['newsletterIds']);

        $this->dispatch('close-slide-over', 'client-form');
        $this->resetForm();
        $this->toast('Client saved.');
    }

    public function delete(int $clientId): void
    {
        $client = $this->ownedClient($clientId);
        $this->authorize('delete', $client);
        $client->delete();
        $this->toast('Client removed. Their publications were kept.');
    }

    public function showContract(int $clientId): void
    {
        $this->contractClientId = $this->ownedClient($clientId)->id;
        $this->dispatch('open-modal', 'contract');
    }

    public function render(): View
    {
        $clients = $this->user()->clients()->with('newsletters:id,name')->orderBy('name')->get();
        $contractClient = $this->contractClientId ? $clients->firstWhere('id', $this->contractClientId) : null;

        return view('livewire.agency.index', [
            'clients' => $clients,
            'packages' => config('agency.packages', []),
            'tips' => config('agency.pricing_tips', []),
            'statuses' => collect(ContractStatus::cases())->mapWithKeys(fn (ContractStatus $status) => [$status->value => $status->label()])->all(),
            'publications' => $this->user()->newsletters()->orderBy('name')->pluck('name', 'id'),
            'monthlyRevenue' => $clients->where('contract_status', ContractStatus::Signed)->sum('monthly_fee'),
            'contract' => $contractClient ? $this->contractFor($contractClient) : null,
        ]);
    }

    private function contractFor(Client $client): string
    {
        $package = collect(config('agency.packages', []))->firstWhere('key', $client->package) ?? ['name' => $client->package, 'issues_per_month' => ''];

        return strtr((string) config('agency.contract', ''), [
            '{agency_name}' => $this->agencyName ?: $this->user()->name,
            '{client_name}' => $client->name,
            '{client_email}' => $client->contact_email ?: '[client email]',
            '{package_name}' => $package['name'],
            '{monthly_fee}' => '$'.number_format((float) $client->monthly_fee, 2),
            '{issues_per_month}' => (string) $package['issues_per_month'],
            '{start_date}' => $this->startDate ?: now()->toDateString(),
            '{notice_days}' => (string) $this->noticeDays,
        ]);
    }

    private function ownedClient(int $clientId): Client
    {
        $client = $this->user()->clients()->findOrFail($clientId);
        $this->authorize('update', $client);

        return $client;
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'contactEmail', 'notes', 'newsletterIds');
        $this->contractStatus = ContractStatus::Draft->value;
        $this->usePackage('starter');
        $this->resetValidation();
    }
}
