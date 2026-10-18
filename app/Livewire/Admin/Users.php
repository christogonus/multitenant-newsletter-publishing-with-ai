<?php

namespace App\Livewire\Admin;

use App\Actions\DeleteAccount;
use App\Actions\RecordPurchase;
use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Enums\PurchaseSource;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin: buyers, their ledger, revoked accounts, manual grants (which are written to the ledger, never hard-coded)
 * and demo accounts for testing.
 */
#[Title('Admin · Buyers')]
class Users extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public bool $revokedOnly = false;

    #[Url]
    public bool $demoOnly = false;

    public ?int $selectedId = null;

    public string $grantProduct = 'front_end';

    public string $demoName = '';

    public string $demoEmail = '';

    /** @var list<string> */
    public array $demoProducts = ['front_end'];

    /**
     * The new demo account's login, shown once after it is created.
     *
     * @var array{email: string, password: string}|null
     */
    public ?array $demoLogin = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function select(int $userId): void
    {
        $this->selectedId = $userId;
        $this->dispatch('open-slide-over', 'buyer');
    }

    public function grant(RecordPurchase $recordPurchase): void
    {
        $this->authorizeAdmin();
        $this->validate(['grantProduct' => ['required', Rule::enum(Product::class)]]);

        $user = User::query()->findOrFail($this->selectedId);

        $recordPurchase->handle(
            email: $user->email,
            name: $user->name,
            product: Product::from($this->grantProduct),
            action: PurchaseAction::Sale,
            transactionId: 'ADMIN-'.Str::upper(Str::random(16)),
            source: PurchaseSource::AdminGrant,
            payload: ['granted_by' => auth()->id()],
        );

        $this->dispatch('toast', message: 'Granted '.Product::from($this->grantProduct)->label().'.', tone: 'success');
    }

    public function revoke(int $purchaseId, RecordPurchase $recordPurchase): void
    {
        $this->authorizeAdmin();
        $purchase = Purchase::query()->where('action', PurchaseAction::Sale)->findOrFail($purchaseId);

        $recordPurchase->handle(
            email: $purchase->user->email,
            name: $purchase->user->name,
            product: $purchase->product_key,
            action: PurchaseAction::Refund,
            transactionId: $purchase->transaction_id,
            source: PurchaseSource::AdminGrant,
            payload: ['revoked_by' => auth()->id()],
        );

        $this->dispatch('toast', message: 'Revoked.', tone: 'success');
    }

    public function updatedDemoOnly(): void
    {
        $this->resetPage();
    }

    public function openDemoForm(): void
    {
        $this->reset('demoName', 'demoEmail', 'demoProducts', 'demoLogin');
        $this->resetValidation();
        $this->dispatch('open-modal', 'demo-account');
    }

    /**
     * Creates a ready-to-use account with the chosen products, granted through the ledger like any admin grant.
     */
    public function createDemo(RecordPurchase $recordPurchase): void
    {
        $this->authorizeAdmin();

        $this->demoEmail = Str::lower(trim($this->demoEmail));

        $this->validate([
            'demoName' => ['required', 'string', 'max:255'],
            'demoEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'demoProducts' => ['required', 'array', 'min:1'],
            'demoProducts.*' => [Rule::enum(Product::class)],
        ], [
            'demoEmail.unique' => 'An account with that email already exists. Open it and grant products instead.',
            'demoProducts.required' => 'Pick at least one product.',
        ]);

        $password = Str::password(12, symbols: false);

        $user = User::create([
            'name' => $this->demoName,
            'email' => $this->demoEmail,
            'password' => $password,
            'terms_accepted_at' => now(),
        ]);
        $user->forceFill(['is_demo' => true, 'email_verified_at' => now()])->save();

        foreach (array_unique([Product::FrontEnd->value, ...$this->demoProducts]) as $productKey) {
            $recordPurchase->handle(
                email: $user->email,
                name: $user->name,
                product: Product::from($productKey),
                action: PurchaseAction::Sale,
                transactionId: 'DEMO-'.Str::upper(Str::random(16)),
                source: PurchaseSource::AdminGrant,
                payload: ['granted_by' => auth()->id(), 'demo' => true],
            );
        }

        $this->demoLogin = ['email' => $user->email, 'password' => $password];
        $this->reset('demoName', 'demoEmail', 'demoProducts');
        $this->dispatch('toast', message: 'Demo account created.', tone: 'success');
    }

    /**
     * Removes a demo account and everything made in it. Real buyers can never be deleted from here.
     */
    public function deleteDemo(int $userId, DeleteAccount $deleteAccount): void
    {
        $this->authorizeAdmin();

        $user = User::query()->where('is_demo', true)->where('is_admin', false)->findOrFail($userId);
        $deleteAccount->handle($user);

        if ($this->selectedId === $userId) {
            $this->selectedId = null;
            $this->dispatch('close-slide-over', 'buyer');
        }

        $this->dispatch('toast', message: 'Demo account deleted.', tone: 'success');
    }

    public function render(): View
    {
        $users = User::query()
            ->withCount('newsletters')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('email', 'like', '%'.$this->search.'%')->orWhere('name', 'like', '%'.$this->search.'%')))
            ->when($this->revokedOnly, fn ($query) => $query->whereHas('purchases', fn ($purchases) => $purchases->whereIn('action', [PurchaseAction::Refund, PurchaseAction::Chargeback])))
            ->when($this->demoOnly, fn ($query) => $query->where('is_demo', true))
            ->latest()
            ->paginate(25);

        $selected = $this->selectedId ? User::query()->with(['purchases' => fn ($query) => $query->latest()])->find($this->selectedId) : null;

        return view('livewire.admin.users', [
            'users' => $users,
            'selected' => $selected,
            'products' => collect(Product::cases())->mapWithKeys(fn (Product $product) => [$product->value => $product->label()])->all(),
            'reversedTxns' => $selected ? $selected->purchases->filter(fn (Purchase $purchase) => $purchase->action->revokes())->map(fn (Purchase $purchase) => $purchase->transaction_id.'|'.$purchase->product_key->value)->all() : [],
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }
}
