<?php

namespace App\Livewire\Reseller;

use App\Enums\Product;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\LicenseCode;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Reseller')]
class Index extends Component
{
    use InteractsWithPublication, WithPagination;

    public int $quantity = 10;

    public string $brand = '';

    public string $slug = '';

    #[Url]
    public string $tab = 'codes';

    public function mount(): void
    {
        $this->brand = (string) $this->user()->reseller_brand;
        $this->slug = (string) $this->user()->reseller_slug;
    }

    public function generate(): void
    {
        $user = $this->user();
        $remaining = $this->remaining();

        $this->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.max(1, min(100, $remaining))]], [
            'quantity.max' => $remaining > 0 ? "You can create up to {$remaining} more codes." : 'You have used all your codes.',
        ]);

        if ($remaining < 1) {
            $this->addError('quantity', 'You have used all your codes.');

            return;
        }

        $created = DB::transaction(function () use ($user) {
            // Lock the reseller row and recount, so parallel requests cannot go over the quota.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($this->quantity > $this->remaining()) {
                return false;
            }

            for ($i = 0; $i < $this->quantity; $i++) {
                $user->licenseCodes()->create(['code' => $this->newCode(), 'product_key' => Product::FrontEnd]);
            }

            return true;
        });

        if (! $created) {
            $this->addError('quantity', 'You can create up to '.$this->remaining().' more codes.');

            return;
        }

        $this->toast($this->quantity.' new codes ready. Each one works once.');
    }

    public function revoke(int $codeId): void
    {
        $code = $this->user()->licenseCodes()->available()->findOrFail($codeId);
        $this->authorize('update', $code);
        $code->update(['revoked_at' => now()]);
        $this->toast('Code cancelled. It can no longer be used.');
    }

    public function saveBrand(): void
    {
        $user = $this->user();

        $this->slug = Str::slug($this->slug);

        $this->validate([
            'brand' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9-]+$/', Rule::unique('users', 'reseller_slug')->ignore($user->id)],
        ], [
            'slug.unique' => 'That link name is taken. Try another.',
        ]);

        $user->forceFill(['reseller_brand' => $this->brand, 'reseller_slug' => $this->slug])->save();
        $this->toast('Your sign-up page is ready.');
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.reseller.index', [
            'codes' => $user->licenseCodes()->with('redeemer:id,email')->latest('id')->paginate(25),
            'quota' => $this->entitlements()->resellerCodeQuota($user),
            'used' => $user->licenseCodes()->whereNull('revoked_at')->count(),
            'redeemed' => $user->licenseCodes()->whereNotNull('redeemed_at')->count(),
            'remaining' => $this->remaining(),
            'signupUrl' => $user->reseller_slug ? route('redeem.show', ['reseller' => $user->reseller_slug]) : null,
            'assets' => config('reseller'),
        ]);
    }

    private function remaining(): int
    {
        $user = $this->user();

        return max(0, $this->entitlements()->resellerCodeQuota($user) - $user->licenseCodes()->whereNull('revoked_at')->count());
    }

    private function newCode(): string
    {
        do {
            $groups = array_map(
                fn () => strtr(Str::upper(Str::random(4)), ['0' => '7', 'O' => 'P', '1' => '8', 'I' => 'J', 'L' => 'M']),
                range(1, 3),
            );
            $code = 'NPAI-'.implode('-', $groups);
        } while (LicenseCode::query()->where('code', $code)->exists());

        return $code;
    }
}
