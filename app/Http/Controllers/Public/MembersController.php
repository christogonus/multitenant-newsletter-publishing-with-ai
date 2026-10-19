<?php

namespace App\Http\Controllers\Public;

use App\Enums\PaidPlan;
use App\Enums\PaymentClaimStatus;
use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubscribeRequest;
use App\Integrations\Payments\PaymentException;
use App\Integrations\Payments\PaymentGateways;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Notifications\ReaderPaymentClaimed;
use App\Support\PaidReaders;
use App\Support\PublicLinks;
use App\Support\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * The paying-reader area of a publication (OTO 4): confirm an email with a code, subscribe through
 * the owner's Stripe or PayPal, then go back to the locked issue.
 */
class MembersController extends Controller
{
    public function __construct(private PaidReaders $paidReaders) {}

    public function show(Request $request): Response
    {
        $newsletter = $this->newsletter($request);

        if (is_string($next = $request->query('next')) && preg_match('/^[a-z0-9-]{1,200}$/', $next)) {
            $request->session()->put('paid_reader_next.'.$newsletter->id, $next);
        }

        $email = $this->paidReaders->signedInEmail($request, $newsletter);
        $subscriber = $this->paidReaders->subscriber($newsletter, $email);

        return response()->view('public.members', [
            'newsletter' => $newsletter,
            'theme' => Theme::for($newsletter),
            'email' => $email,
            'pendingEmail' => $request->session()->get('paid_reader_pending.'.$newsletter->id),
            'subscriber' => $subscriber,
            'hasAccess' => $email && $this->paidReaders->hasAccess($newsletter, $email),
            'plans' => $this->paidReaders->plans($newsletter),
            'accounts' => $this->paidReaders->accounts($newsletter),
            'pendingClaim' => $email ? $newsletter->paymentClaims()->pending()->where('email', $email)->latest()->first() : null,
            'nextUrl' => $this->nextUrl($request, $newsletter),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function sendCode(SubscribeRequest $request): RedirectResponse
    {
        $newsletter = $this->newsletter($request);
        $email = $request->validated('email');

        if (! $request->looksLikeABot() && ! $this->paidReaders->sendCode($newsletter, $email)) {
            return back()->withInput()->withErrors(['email' => 'We have sent a few codes already. Check your inbox, or try again in 10 minutes.']);
        }

        $request->session()->put('paid_reader_pending.'.$newsletter->id, strtolower(trim($email)));

        return redirect(PublicLinks::route('members'))->with('status', 'We emailed you a 6-digit code. It can take a minute to arrive.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $newsletter = $this->newsletter($request);
        $request->validate(['code' => ['required', 'string', 'max:10']], ['code.required' => 'Enter the code from the email.']);

        $email = $request->session()->get('paid_reader_pending.'.$newsletter->id);

        if (! $email || ! $this->paidReaders->checkCode($newsletter, $email, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'That code is not right, or it has expired. Check the email or ask for a new code.']);
        }

        $this->paidReaders->signIn($request, $newsletter, $email);
        $request->session()->forget('paid_reader_pending.'.$newsletter->id);

        if ($this->paidReaders->hasAccess($newsletter, $email) && ($nextUrl = $this->nextUrl($request, $newsletter))) {
            return redirect($nextUrl);
        }

        return redirect(PublicLinks::route('members'));
    }

    public function signOut(Request $request): RedirectResponse
    {
        $this->paidReaders->signOut($request, $this->newsletter($request));

        return redirect(PublicLinks::route('members'));
    }

    public function checkout(Request $request, PaymentGateways $gateways): RedirectResponse
    {
        $newsletter = $this->newsletter($request);
        $email = $this->paidReaders->signedInEmail($request, $newsletter);

        $validated = $request->validate([
            'provider' => ['required', Rule::enum(PaymentProvider::class)->only(PaymentProvider::connectable())],
            'plan' => ['required', Rule::enum(PaidPlan::class)],
        ]);

        $provider = PaymentProvider::from($validated['provider']);
        $plan = PaidPlan::from($validated['plan']);
        $account = $this->paidReaders->account($newsletter, $provider);

        if (! $email || ! $newsletter->hasPaidSubscriptions() || ! $account || $newsletter->paidPriceCents($plan) === null) {
            return redirect(PublicLinks::route('members'));
        }

        try {
            $checkoutUrl = $gateways->for($provider)->startCheckout(
                $account,
                $plan,
                $email,
                $this->paidReaders->reference($newsletter, $email),
                PublicLinks::route('members.complete', ['provider' => $provider->value]),
                PublicLinks::route('members'),
            );
        } catch (PaymentException $exception) {
            report($exception);

            return redirect(PublicLinks::route('members'))->withErrors(['payment' => 'We could not open the payment page. Please try again in a few minutes.']);
        }

        return redirect()->away($checkoutUrl);
    }

    /**
     * The reader says they paid by the owner's manual method. The owner is told and activates them after checking.
     */
    public function claimManualPayment(Request $request): RedirectResponse
    {
        $newsletter = $this->newsletter($request);
        $email = $this->paidReaders->signedInEmail($request, $newsletter);

        $validated = $request->validate([
            'plan' => ['required', Rule::enum(PaidPlan::class)],
            'reference' => ['nullable', 'string', 'max:500'],
        ]);

        $plan = PaidPlan::from($validated['plan']);

        if (! $email || ! $newsletter->acceptsManualPayments() || $newsletter->paidPriceCents($plan) === null) {
            return redirect(PublicLinks::route('members'));
        }

        // One open claim per reader: sending again updates it instead of piling up emails to the owner.
        $claim = $newsletter->paymentClaims()->pending()->where('email', $email)->first();
        $isNew = ! $claim;

        $claim ??= $newsletter->paymentClaims()->make(['email' => $email, 'status' => PaymentClaimStatus::Pending]);
        $claim->fill(['plan' => $plan, 'reference' => trim((string) ($validated['reference'] ?? '')) ?: null])->save();

        if ($isNew) {
            Notification::route('mail', $newsletter->paid_manual_contact)->notify(new ReaderPaymentClaimed($claim));
        }

        return redirect(PublicLinks::route('members'))->with('status', 'Thank you. '.$newsletter->name.' will check your payment and turn on your access.');
    }

    public function complete(Request $request, PaymentGateways $gateways, string $provider): RedirectResponse
    {
        $newsletter = $this->newsletter($request);
        $email = $this->paidReaders->signedInEmail($request, $newsletter);
        $provider = PaymentProvider::tryFrom($provider);
        $account = $provider && in_array($provider, PaymentProvider::connectable(), true) ? $this->paidReaders->account($newsletter, $provider) : null;

        if (! $email || ! $account) {
            return redirect(PublicLinks::route('members'));
        }

        try {
            $access = $gateways->for($provider)->confirmCheckout($account, $request, $this->paidReaders->reference($newsletter, $email));
        } catch (PaymentException $exception) {
            return redirect(PublicLinks::route('members'))->withErrors(['payment' => $exception->getMessage().' '.$exception->fix]);
        }

        $this->paidReaders->grant($newsletter, $email, $access->paidUntil, $provider, $access->plan, $access->reference, $request->ip());

        return redirect($this->nextUrl($request, $newsletter) ?? PublicLinks::route('members'))
            ->with('status', 'Thank you for subscribing. You can now read every issue.');
    }

    private function newsletter(Request $request): Newsletter
    {
        $newsletter = $request->attributes->get('newsletter');

        abort_unless($newsletter->hasPaidSubscriptions(), 404);

        return $newsletter;
    }

    /**
     * The locked issue the reader came from, if it still exists.
     */
    private function nextUrl(Request $request, Newsletter $newsletter): ?string
    {
        $slug = $request->session()->get('paid_reader_next.'.$newsletter->id);

        $exists = $slug && Issue::query()->publiclyVisible()->whereBelongsTo($newsletter)->where('public_slug', $slug)->exists();

        return $exists ? PublicLinks::route('issue', ['slug' => $slug]) : null;
    }
}
