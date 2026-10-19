<?php

namespace App\Livewire\Admin;

use App\Actions\DeleteAccount;
use App\Actions\RecordPurchase;
use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Enums\PurchaseSource;
use App\Models\BumpOffer;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\UpgradeUnlocked;
use App\Notifications\WelcomeBuyer;
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

    #[Url]
    public bool $suspendedOnly = false;

    public string $suspensionReason = '';

    /**
     * The email typed to confirm deleting an account.
     */
    public string $deleteConfirmation = '';

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
        $this->reset('suspensionReason', 'deleteConfirmation');
        $this->resetValidation();
        $this->dispatch('open-slide-over', 'buyer');
    }

    public function grant(RecordPurchase $recordPurchase): void
    {
        $this->authorizeAdmin();

        // "bump:12" grants an order bump (its files, and the product it unlocks); anything else is a product.
        $bumpOffer = str_starts_with($this->grantProduct, 'bump:')
            ? BumpOffer::query()->find((int) substr($this->grantProduct, 5))
            : null;

        if (! $bumpOffer) {
            $this->validate(['grantProduct' => ['required', Rule::enum(Product::class)]]);
        }

        $user = User::query()->findOrFail($this->selectedId);
        $transactionId = 'ADMIN-'.Str::upper(Str::random(16));
        $products = $bumpOffer ? array_filter([$bumpOffer->unlocks_product]) : [Product::from($this->grantProduct)];

        if ($bumpOffer) {
            $recordPurchase->handle($user->email, $user->name, null, PurchaseAction::Sale, $transactionId, source: PurchaseSource::AdminGrant, payload: ['granted_by' => auth()->id()], bumpOffer: $bumpOffer, user: $user);
        }

        foreach ($products as $product) {
            $recordPurchase->handle(
                email: $user->email,
                name: $user->name,
                product: $product,
                action: PurchaseAction::Sale,
                transactionId: $transactionId,
                source: PurchaseSource::AdminGrant,
                payload: ['granted_by' => auth()->id()],
                user: $user,
            );
        }

        $this->dispatch('toast', message: 'Granted '.($bumpOffer?->name ?? Product::from($this->grantProduct)->label()).'.', tone: 'success');
    }

    public function revoke(int $purchaseId, RecordPurchase $recordPurchase): void
    {
        $this->authorizeAdmin();
        $purchase = Purchase::query()->where('action', PurchaseAction::Sale)->findOrFail($purchaseId);

        $recordPurchase->handle(
            email: $purchase->buyer_email ?? $purchase->user->email,
            name: $purchase->user->name,
            product: $purchase->product_key,
            action: PurchaseAction::Refund,
            transactionId: $purchase->transaction_id,
            source: PurchaseSource::AdminGrant,
            payload: ['revoked_by' => auth()->id()],
            bumpOffer: $purchase->bumpOffer,
            user: $purchase->user,
        );

        $this->dispatch('toast', message: 'Revoked.', tone: 'success');
    }

    /**
     * Sends a fresh welcome email with a set-password link. For an account that already has a password it works like a reset.
     */
    public function resendWelcome(): void
    {
        $this->authorizeAdmin();

        $user = User::query()->findOrFail($this->selectedId);
        $user->notify(new WelcomeBuyer);

        $this->dispatch('toast', message: 'Welcome email sent to '.$user->email.'.', tone: 'success');
    }

    /**
     * Re-sends the "your upgrade is unlocked" email for everything still standing on the purchase's transaction.
     */
    public function resendUpgrade(int $purchaseId): void
    {
        $this->authorizeAdmin();

        $purchase = Purchase::query()->with('user')->standing()->findOrFail($purchaseId);
        $purchase->user->notify(new UpgradeUnlocked($purchase->transaction_id));

        $this->dispatch('toast', message: 'Upgrade email sent to '.$purchase->user->email.'.', tone: 'success');
    }

    public function updatedDemoOnly(): void
    {
        $this->resetPage();
    }

    public function updatedSuspendedOnly(): void
    {
        $this->resetPage();
    }

    /**
     * Puts the account on hold: the dashboard closes and every newsletter shows a "coming soon" page. Nothing is deleted.
     */
    public function suspend(): void
    {
        $this->authorizeAdmin();
        $this->validate(['suspensionReason' => ['nullable', 'string', 'max:500']]);

        $user = $this->manageableUser();
        $user->forceFill(['suspended_at' => now(), 'suspension_reason' => trim($this->suspensionReason) ?: null])->save();

        $this->reset('suspensionReason');
        $this->dispatch('toast', message: $user->email.' is suspended. Their newsletters now show "coming soon".', tone: 'success');
    }

    public function unsuspend(): void
    {
        $this->authorizeAdmin();

        $user = $this->manageableUser();
        $user->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();

        $this->dispatch('toast', message: $user->email.' is active again.', tone: 'success');
    }

    /**
     * Erases a buyer with every newsletter, issue, reader, upload and purchase record. The email must be typed to confirm.
     */
    public function deleteUser(DeleteAccount $deleteAccount): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag('deleteConfirmation');

        $user = $this->manageableUser();

        if (mb_strtolower(trim($this->deleteConfirmation)) !== mb_strtolower($user->email)) {
            $this->addError('deleteConfirmation', 'Type the email exactly to confirm.');

            return;
        }

        $email = $user->email;
        $deleteAccount->handle($user);

        $this->reset('selectedId', 'deleteConfirmation');
        $this->dispatch('close-slide-over', 'buyer');
        $this->dispatch('toast', message: $email.' and everything in the account were deleted.', tone: 'success');
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
                user: $user,
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
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('email', 'like', '%'.$this->search.'%')
                ->orWhere('name', 'like', '%'.$this->search.'%')
                ->orWhereHas('purchases', fn ($purchases) => $purchases->where('buyer_email', 'like', '%'.$this->search.'%'))))
            ->when($this->revokedOnly, fn ($query) => $query->whereHas('purchases', fn ($purchases) => $purchases->whereIn('action', [PurchaseAction::Refund, PurchaseAction::Chargeback])))
            ->when($this->demoOnly, fn ($query) => $query->where('is_demo', true))
            ->when($this->suspendedOnly, fn ($query) => $query->whereNotNull('suspended_at'))
            ->latest()
            ->paginate(25);

        $selected = $this->selectedId ? User::query()->with(['purchases' => fn ($query) => $query->latest(), 'purchases.bumpOffer'])->withCount('newsletters')->find($this->selectedId) : null;

        $products = collect(Product::cases())->mapWithKeys(fn (Product $product) => [$product->value => $product->label()])->all();

        return view('livewire.admin.users', [
            'users' => $users,
            'selected' => $selected,
            'products' => $products,
            'grantOptions' => $products + BumpOffer::query()->get()
                ->mapWithKeys(fn (BumpOffer $bumpOffer) => ['bump:'.$bumpOffer->id => 'Order bump: '.$bumpOffer->name.' ('.$bumpOffer->product_key->label().')'])
                ->all(),
            'reversedTxns' => $selected ? $selected->purchases->filter(fn (Purchase $purchase) => $purchase->action->revokes())->map(fn (Purchase $purchase) => $purchase->transaction_id.'|'.$purchase->item_key)->all() : [],
        ]);
    }

    /**
     * The open account, if an admin may suspend or delete it: never an admin account, and never your own.
     */
    private function manageableUser(): User
    {
        $user = User::query()->findOrFail($this->selectedId);

        abort_if($user->is_admin || $user->is(auth()->user()), 403);

        return $user;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }
}
