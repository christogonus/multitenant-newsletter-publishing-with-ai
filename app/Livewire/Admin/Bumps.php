<?php

namespace App\Livewire\Admin;

use App\Enums\Product;
use App\Http\Controllers\Admin\BumpFileUploadController;
use App\Models\BumpOffer;
use App\Models\BumpOfferFile;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin: the order bump on each product's checkout. LaunchpadJV only says "the bump was taken", so the
 * product's one active bump is what the buyer gets: files to download, a built-in product, or both.
 */
#[Title('Admin · Order bumps')]
class Bumps extends Component
{
    /**
     * The bump open in the editor; in the URL so the file upload form can bring the admin back to it.
     */
    #[Url(as: 'editing')]
    public ?int $editingId = null;

    /** @var array{product_key: string, name: string, description: string, price: string, unlocks_product: string} */
    public array $form = ['product_key' => 'front_end', 'name' => '', 'description' => '', 'price' => '17', 'unlocks_product' => ''];

    public function mount(): void
    {
        if ($this->editingId) {
            $this->edit($this->editingId);
        }
    }

    public function edit(?int $bumpOfferId = null): void
    {
        $this->authorizeAdmin();
        $this->resetValidation();
        $existing = $bumpOfferId ? BumpOffer::query()->findOrFail($bumpOfferId) : null;

        $this->editingId = $existing?->id;
        $this->form = [
            'product_key' => $existing?->product_key->value ?? Product::FrontEnd->value,
            'name' => (string) $existing?->name,
            'description' => (string) $existing?->description,
            'price' => $existing ? (string) (float) $existing->price : '17',
            'unlocks_product' => (string) $existing?->unlocks_product?->value,
        ];

        $this->dispatch('open-slide-over', 'bump-form');
    }

    public function save(): void
    {
        $this->authorizeAdmin();

        $validated = $this->validate([
            'form.product_key' => ['required', Rule::in(array_keys($this->sellingProducts()))],
            'form.name' => ['required', 'string', 'max:120'],
            'form.description' => ['nullable', 'string', 'max:1000'],
            'form.price' => ['required', 'numeric', 'min:0', 'max:10000'],
            'form.unlocks_product' => ['nullable', Rule::enum(Product::class)],
        ], [
            'form.name.required' => 'Give the bump the name buyers saw on the checkout.',
        ])['form'];

        $existing = $this->editingId ? BumpOffer::query()->findOrFail($this->editingId) : null;

        $bumpOffer = BumpOffer::query()->updateOrCreate(['id' => $existing?->id], [
            'product_key' => $validated['product_key'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'price' => $validated['price'],
            'unlocks_product' => $validated['unlocks_product'] ?: null,
        ]);

        // A new bump goes live at once unless its product already has one; moving a live bump keeps it live there.
        if ((! $existing && ! BumpOffer::activeFor($bumpOffer->product_key)) || $bumpOffer->is_active) {
            $bumpOffer->activate();
        }

        $this->editingId = $bumpOffer->id;
        $this->dispatch('toast', message: 'Saved. Add its files below.', tone: 'success');
    }

    public function activate(int $bumpOfferId): void
    {
        $this->authorizeAdmin();
        $bumpOffer = BumpOffer::query()->findOrFail($bumpOfferId);
        $bumpOffer->activate();
        $this->dispatch('toast', message: $bumpOffer->name.' is now the bump on '.$bumpOffer->product_key->label().'.', tone: 'success');
    }

    public function deactivate(int $bumpOfferId): void
    {
        $this->authorizeAdmin();
        BumpOffer::query()->whereKey($bumpOfferId)->update(['is_active' => false]);
        $this->dispatch('toast', message: 'Switched off. Buyers who already have it keep their downloads.', tone: 'success');
    }

    public function deleteFile(int $fileId): void
    {
        $this->authorizeAdmin();
        BumpOfferFile::query()->findOrFail($fileId)->delete();
        $this->dispatch('toast', message: 'File removed. Buyers no longer see it.', tone: 'success');
    }

    /**
     * Deletes a bump nobody has bought. A bump with buyers is switched off instead, so their downloads keep working.
     */
    public function delete(int $bumpOfferId): void
    {
        $this->authorizeAdmin();
        $bumpOffer = BumpOffer::query()->withCount('purchases')->findOrFail($bumpOfferId);

        if ($bumpOffer->purchases_count > 0) {
            $bumpOffer->update(['is_active' => false]);
            $this->dispatch('toast', message: 'Buyers own this bump, so it was switched off instead of deleted.', tone: 'warning');

            return;
        }

        $bumpOffer->files->each->delete();
        $bumpOffer->delete();

        if ($this->editingId === $bumpOfferId) {
            $this->editingId = null;
            $this->dispatch('close-slide-over', 'bump-form');
        }

        $this->dispatch('toast', message: 'Deleted.', tone: 'success');
    }

    public function closeEditor(): void
    {
        $this->editingId = null;
    }

    public function render(): View
    {
        return view('livewire.admin.bumps', [
            'bumps' => BumpOffer::query()->withCount(['files', 'purchases'])->orderBy('product_key')->latest('id')->get()
                ->sortBy(fn (BumpOffer $offer) => array_search($offer->product_key, Product::cases(), true)),
            'editing' => $this->editingId ? BumpOffer::query()->with('files')->find($this->editingId) : null,
            'products' => $this->sellingProducts(),
            'unlockable' => ['' => 'Nothing, files only'] + collect(Product::cases())->mapWithKeys(fn (Product $product) => [$product->value => $product->label()])->all(),
            'maxFileMegabytes' => intdiv(BumpFileUploadController::MAX_FILE_KILOBYTES, 1024),
            'uploadErrors' => session('errors')?->all() ?? [],
        ]);
    }

    /**
     * Products whose checkout can carry a bump: everything except the built-in vault, which is itself a bump.
     *
     * @return array<string, string>
     */
    private function sellingProducts(): array
    {
        return collect(Product::cases())
            ->reject(fn (Product $product) => $product === Product::BumpVault)
            ->mapWithKeys(fn (Product $product) => [$product->value => $product->label()])
            ->all();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }
}
