<?php

namespace App\Livewire\Sponsors;

use App\Enums\ConnectionStatus;
use App\Enums\PaymentClaimStatus;
use App\Enums\PaymentProvider;
use App\Enums\Product;
use App\Integrations\Payments\PaymentException;
use App\Integrations\Payments\PaymentGateways;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\PaymentAccount;
use App\Support\PaidReaders;
use App\Support\PublicPageCache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Paid subscriptions (OTO 4): prices, the owner's Stripe and PayPal accounts, and readers with paid access.
 */
class PaidSubscriptions extends Component
{
    use InteractsWithPublication;

    public const CURRENCIES = ['USD' => 'US dollar', 'EUR' => 'Euro', 'GBP' => 'British pound', 'CAD' => 'Canadian dollar', 'AUD' => 'Australian dollar'];

    public bool $paidEnabled = false;

    public string $monthlyPrice = '';

    public string $yearlyPrice = '';

    public string $currency = 'USD';

    public string $perks = '';

    public ?string $editing = null;

    /** @var array<string, string> */
    public array $credentials = [];

    public bool $sandbox = false;

    public bool $manualEnabled = false;

    public string $manualInstructions = '';

    public string $manualContact = '';

    public string $grantEmail = '';

    public string $grantUntil = '';

    public function mount(): void
    {
        $this->ensureOwnsUpsell();
        $newsletter = $this->newsletter;

        $this->paidEnabled = $newsletter->paid_enabled;
        $this->monthlyPrice = $newsletter->paid_monthly_cents ? number_format($newsletter->paid_monthly_cents / 100, 2, '.', '') : '';
        $this->yearlyPrice = $newsletter->paid_yearly_cents ? number_format($newsletter->paid_yearly_cents / 100, 2, '.', '') : '';
        $this->currency = $newsletter->paid_currency ?: 'USD';
        $this->perks = (string) $newsletter->paid_perks;
        $this->manualEnabled = $newsletter->paid_manual_enabled;
        $this->manualInstructions = (string) $newsletter->paid_manual_instructions;
        $this->manualContact = $newsletter->paid_manual_contact ?: $this->user()->email;
        $this->grantUntil = now()->addYear()->toDateString();
    }

    public function saveSettings(): void
    {
        $this->ensureOwnsUpsell();
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $this->validate([
            'monthlyPrice' => [Rule::requiredIf($this->paidEnabled && trim($this->yearlyPrice) === ''), 'nullable', 'numeric', 'min:1', 'max:10000'],
            'yearlyPrice' => ['nullable', 'numeric', 'min:1', 'max:10000'],
            'currency' => ['required', Rule::in(array_keys(self::CURRENCIES))],
            'perks' => ['nullable', 'string', 'max:1000'],
        ], [
            'monthlyPrice.required' => 'Set a monthly or a yearly price before you turn paid subscriptions on.',
            'monthlyPrice.min' => 'The lowest price is 1.',
            'yearlyPrice.min' => 'The lowest price is 1.',
        ]);

        $newsletter->update([
            'paid_enabled' => $this->paidEnabled,
            'paid_monthly_cents' => $this->toCents($this->monthlyPrice),
            'paid_yearly_cents' => $this->toCents($this->yearlyPrice),
            'paid_currency' => $this->currency,
            'paid_perks' => trim($this->perks) ?: null,
        ]);

        PublicPageCache::bust($newsletter);
        $this->toast($this->paidEnabled ? 'Saved. Issues you mark "Paying readers only" are now locked.' : 'Saved. Paid subscriptions are off, so every issue is free to read.');
    }

    /**
     * Payment by bank transfer, crypto and so on: readers follow the owner's instructions, then email the owner,
     * who turns their access on with "Give a reader access".
     */
    public function saveManualPayments(): void
    {
        $this->ensureOwnsUpsell();
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $this->validate([
            'manualInstructions' => [Rule::requiredIf($this->manualEnabled), 'nullable', 'string', 'max:2000'],
            'manualContact' => [Rule::requiredIf($this->manualEnabled), 'nullable', 'string', 'email:rfc', 'max:255'],
        ], [
            'manualInstructions.required' => 'Tell readers how to pay you, such as your bank details or wallet address.',
            'manualContact.required' => 'Add the email readers should write to after they pay.',
        ]);

        $newsletter->update([
            'paid_manual_enabled' => $this->manualEnabled,
            'paid_manual_instructions' => trim($this->manualInstructions) ?: null,
            'paid_manual_contact' => trim($this->manualContact) ?: null,
        ]);

        $this->toast($this->manualEnabled ? 'Saved. Readers now see your payment instructions.' : 'Saved. Manual payments are off.');
    }

    public function edit(string $provider): void
    {
        $this->ensureOwnsUpsell();
        $provider = PaymentProvider::from($provider);
        abort_unless(in_array($provider, PaymentProvider::connectable(), true), 404);

        $this->editing = $provider->value;
        $this->resetValidation();

        $existing = $this->accountFor($provider);
        $this->sandbox = $existing?->credential('mode') === 'sandbox';
        $this->credentials = collect(app(PaymentGateways::class)->for($provider)->fields())
            ->mapWithKeys(fn (array $field) => [$field['key'] => $field['secret'] ? '' : (string) $existing?->credential($field['key'])])
            ->all();
    }

    public function cancel(): void
    {
        $this->reset('editing', 'credentials', 'sandbox');
    }

    public function connect(PaymentGateways $gateways): void
    {
        $this->ensureOwnsUpsell();
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $provider = PaymentProvider::from((string) $this->editing);
        abort_unless(in_array($provider, PaymentProvider::connectable(), true), 404);

        $gateway = $gateways->for($provider);
        $existing = $this->accountFor($provider);

        $this->validate(collect($gateway->fields())->mapWithKeys(fn (array $field) => ['credentials.'.$field['key'] => [
            $field['required'] && ! ($field['secret'] && $existing?->credential($field['key'])) ? 'required' : 'nullable',
            'string',
            'max:500',
        ]])->all(), ['credentials.*.required' => 'Please fill this in.']);

        $values = collect($this->credentials)->map(fn ($value) => trim((string) $value))->filter(fn ($value) => $value !== '')->all();
        $credentials = array_merge($existing?->credentials ?? [], $values);

        if ($provider === PaymentProvider::PayPal) {
            $credentials['mode'] = $this->sandbox ? 'sandbox' : 'live';
        }

        $account = $existing ?? new PaymentAccount(['newsletter_id' => $newsletter->id, 'provider' => $provider]);
        $keysChanged = $account->credentials !== $credentials;
        $account->credentials = $credentials;

        try {
            $gateway->test($account);
        } catch (PaymentException $exception) {
            $this->addError('connection', $exception->getMessage().' '.$exception->fix);

            return;
        }

        // PayPal plans belong to one PayPal account, so new keys start fresh.
        $account->fill([
            'status' => ConnectionStatus::Connected,
            'last_error' => null,
            'details' => $keysChanged ? null : $account->details,
        ])->save();

        $this->reset('editing', 'credentials', 'sandbox');
        $this->toast($provider->label().' is connected. Readers can now pay with it.');
    }

    public function disconnect(int $accountId): void
    {
        $this->ensureOwnsUpsell();
        $this->authorize('update', $this->newsletter);

        $this->newsletter->paymentAccounts()->findOrFail($accountId)->delete();
        $this->toast('Disconnected. Readers who already pay keep their access until their paid time runs out.');
    }

    public function grantAccess(PaidReaders $paidReaders): void
    {
        $this->ensureOwnsUpsell();
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $this->validate([
            'grantEmail' => ['required', 'string', 'email:rfc', 'max:255'],
            'grantUntil' => ['required', 'date', 'after:today'],
        ], [
            'grantEmail.required' => 'Enter the reader\'s email.',
            'grantUntil.after' => 'Pick a date in the future.',
        ]);

        $subscriber = $paidReaders->subscriber($newsletter, $this->grantEmail);

        if ($subscriber?->hasPaidAccess() && $subscriber->paid_via !== PaymentProvider::Manual) {
            $this->addError('grantEmail', 'This reader already pays through '.$subscriber->paid_via->label().'.');

            return;
        }

        $paidReaders->grant($newsletter, $this->grantEmail, Carbon::parse($this->grantUntil)->endOfDay(), PaymentProvider::Manual);

        $this->reset('grantEmail');
        $this->toast('Done. They can now sign in with their email and read paid issues.');
    }

    /**
     * Turns on access for a reader who said they paid by a manual method, for one period of their plan.
     * A reader who still has access by hand gets the new period added on after it.
     */
    public function activateClaim(int $claimId, PaidReaders $paidReaders): void
    {
        $this->ensureOwnsUpsell();
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $claim = $newsletter->paymentClaims()->pending()->findOrFail($claimId);
        $subscriber = $paidReaders->subscriber($newsletter, $claim->email);

        if ($subscriber?->hasPaidAccess() && $subscriber->paid_via !== PaymentProvider::Manual) {
            $this->toast($claim->email.' already pays through '.$subscriber->paid_via->label().'. Decline this one if it is a mistake.', 'warning');

            return;
        }

        $startsFrom = $subscriber?->hasPaidAccess() ? $subscriber->paid_until : now();
        $paidUntil = $claim->plan->after($startsFrom);

        $paidReaders->grant($newsletter, $claim->email, $paidUntil, PaymentProvider::Manual, $claim->plan, $claim->reference);
        $claim->update(['status' => PaymentClaimStatus::Approved, 'resolved_at' => now()]);

        $this->toast($claim->email.' can now read paid issues until '.$paidUntil->toFormattedDateString().'.');
    }

    public function declineClaim(int $claimId): void
    {
        $this->ensureOwnsUpsell();
        $this->authorize('update', $this->newsletter);

        $claim = $this->newsletter->paymentClaims()->pending()->findOrFail($claimId);
        $claim->update(['status' => PaymentClaimStatus::Declined, 'resolved_at' => now()]);

        $this->toast('Declined. Let '.$claim->email.' know why, so they can sort it out.');
    }

    public function revokeAccess(int $subscriberId): void
    {
        $this->ensureOwnsUpsell();
        $this->authorize('update', $this->newsletter);

        $subscriber = $this->newsletter->subscribers()->where('paid_via', PaymentProvider::Manual)->findOrFail($subscriberId);
        $subscriber->update(['paid_until' => null, 'paid_via' => null, 'paid_plan' => null]);

        $this->toast('Paid access removed.');
    }

    public function render(PaymentGateways $gateways): View
    {
        $newsletter = $this->newsletter;
        $accounts = $newsletter->paymentAccounts()->get()->keyBy(fn (PaymentAccount $account) => $account->provider->value);

        return view('livewire.sponsors.paid-subscriptions', [
            'providers' => collect(PaymentProvider::connectable())->map(fn (PaymentProvider $provider) => [
                'provider' => $provider,
                'gateway' => $gateways->for($provider),
                'account' => $accounts->get($provider->value),
            ]),
            'pendingClaims' => $newsletter->paymentClaims()->pending()->oldest()->limit(50)->get(),
            'payingCount' => $newsletter->subscribers()->paying()->count(),
            'payingReaders' => $newsletter->subscribers()->paying()->latest('updated_at')->limit(50)->get(),
            'membersUrl' => $newsletter->publicUrl('members'),
            'currencies' => self::CURRENCIES,
        ]);
    }

    private function accountFor(PaymentProvider $provider): ?PaymentAccount
    {
        return $this->newsletter->paymentAccounts()->where('provider', $provider)->first();
    }

    private function toCents(string $price): ?int
    {
        return trim($price) === '' ? null : (int) round((float) $price * 100);
    }

    /**
     * Livewire updates skip route middleware, so every action checks the upsell again.
     */
    private function ensureOwnsUpsell(): void
    {
        abort_unless($this->user()->owns(Product::Oto4Sponsor), 403);
    }
}
