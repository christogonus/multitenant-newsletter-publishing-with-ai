<?php

namespace App\Integrations\Payments;

use App\Enums\PaidPlan;
use App\Enums\PaymentProvider;
use App\Models\PaymentAccount;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Stripe Checkout in subscription mode, priced inline so the owner does not have to create products in Stripe.
 */
class StripeGateway extends HttpPaymentGateway
{
    public const BASE_URL = 'https://api.stripe.com/v1';

    public function fields(): array
    {
        return [
            ['key' => 'secret_key', 'label' => 'Secret key', 'hint' => 'Starts with sk_live_ (or sk_test_ while you try things out).', 'secret' => true, 'required' => true],
        ];
    }

    public function steps(): array
    {
        return [
            'Sign in to your Stripe dashboard and open Developers, then API keys.',
            'Next to "Secret key", press Reveal and copy the key.',
            'Paste it below and press Test and save.',
        ];
    }

    public function test(PaymentAccount $account): void
    {
        $this->send($account, fn (PendingRequest $http) => $http->get('balance'));
    }

    public function startCheckout(PaymentAccount $account, PaidPlan $plan, string $email, string $reference, string $returnUrl, string $cancelUrl): string
    {
        $newsletter = $account->newsletter;

        $response = $this->send($account, fn (PendingRequest $http) => $http->post('checkout/sessions', [
            'mode' => 'subscription',
            'customer_email' => $email,
            'client_reference_id' => $reference,
            'success_url' => $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => Str::lower($newsletter->paid_currency),
                    'unit_amount' => $newsletter->paidPriceCents($plan),
                    'recurring' => ['interval' => $plan->period()],
                    'product_data' => ['name' => Str::limit($newsletter->name, 200, '').' paid subscription'],
                ],
            ]],
            'metadata' => ['newsletter_id' => $newsletter->id, 'plan' => $plan->value],
            'subscription_data' => ['metadata' => ['newsletter_id' => $newsletter->id, 'plan' => $plan->value]],
        ]));

        return (string) $response->json('url');
    }

    public function confirmCheckout(PaymentAccount $account, Request $request, string $reference): PaidAccess
    {
        $sessionId = (string) $request->query('session_id');

        if (! preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
            throw PaymentException::notCompleted(PaymentProvider::Stripe);
        }

        $session = $this->send($account, fn (PendingRequest $http) => $http->get('checkout/sessions/'.$sessionId, ['expand' => ['subscription']]))->json();
        $subscription = $session['subscription'] ?? null;

        if (($session['client_reference_id'] ?? null) !== $reference
            || ($session['status'] ?? null) !== 'complete'
            || ! is_array($subscription)
            || ! in_array($subscription['status'] ?? null, ['active', 'trialing'], true)) {
            throw PaymentException::notCompleted(PaymentProvider::Stripe);
        }

        $plan = PaidPlan::tryFrom((string) data_get($session, 'metadata.plan')) ?? PaidPlan::Monthly;

        return new PaidAccess($subscription['id'], $this->periodEnd($subscription) ?? $plan->after(now()), $plan);
    }

    public function paidUntil(PaymentAccount $account, string $subscriptionId): ?CarbonInterface
    {
        $subscription = $this->send($account, fn (PendingRequest $http) => $http->get('subscriptions/'.rawurlencode($subscriptionId)))->json();

        return in_array($subscription['status'] ?? null, ['active', 'trialing'], true) ? $this->periodEnd($subscription) : null;
    }

    protected function provider(): PaymentProvider
    {
        return PaymentProvider::Stripe;
    }

    protected function client(PaymentAccount $account): PendingRequest
    {
        return $this->http()
            ->asForm()
            ->baseUrl(self::BASE_URL)
            ->withToken($this->required($account, 'secret_key', 'secret key'));
    }

    /**
     * Newer Stripe API versions keep the period end on the subscription item, older ones on the subscription.
     *
     * @param  array<string, mixed>  $subscription
     */
    private function periodEnd(array $subscription): ?CarbonInterface
    {
        $timestamp = data_get($subscription, 'items.data.0.current_period_end') ?? data_get($subscription, 'current_period_end');

        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }
}
