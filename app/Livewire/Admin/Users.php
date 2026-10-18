<?php

namespace App\Livewire\Admin;

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
 * Admin: buyers, their ledger, revoked accounts and manual grants (which are written to the ledger, never hard-coded).
 */
#[Title('Admin · Buyers')]
class Users extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public bool $revokedOnly = false;

    public ?int $selectedId = null;

    public string $grantProduct = 'front_end';

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
            transactionId: $purchase->wp_txn_id,
            source: PurchaseSource::AdminGrant,
            payload: ['revoked_by' => auth()->id()],
        );

        $this->dispatch('toast', message: 'Revoked.', tone: 'success');
    }

    public function render(): View
    {
        $users = User::query()
            ->withCount('newsletters')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('email', 'like', '%'.$this->search.'%')->orWhere('name', 'like', '%'.$this->search.'%')))
            ->when($this->revokedOnly, fn ($query) => $query->whereHas('purchases', fn ($purchases) => $purchases->whereIn('action', [PurchaseAction::Refund, PurchaseAction::Chargeback])))
            ->latest()
            ->paginate(25);

        $selected = $this->selectedId ? User::query()->with(['purchases' => fn ($query) => $query->latest()])->find($this->selectedId) : null;

        return view('livewire.admin.users', [
            'users' => $users,
            'selected' => $selected,
            'products' => collect(Product::cases())->mapWithKeys(fn (Product $product) => [$product->value => $product->label()])->all(),
            'reversedTxns' => $selected ? $selected->purchases->filter(fn (Purchase $purchase) => $purchase->action->revokes())->pluck('wp_txn_id')->all() : [],
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }
}
