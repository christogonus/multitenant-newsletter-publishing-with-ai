<?php

namespace App\Integrations\Payments;

use App\Enums\PaidPlan;
use App\Enums\PaymentProvider;
use App\Models\PaymentAccount;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * PayPal Subscriptions. The product and one billing plan per price are created on first use and remembered on the account.
 */
class PayPalGateway extends HttpPaymentGateway
{
    public const LIVE_URL = 'https://api-m.paypal.com';

    public const SANDBOX_URL = 'https://api-m.sandbox.paypal.com';

    /**
     * Access tokens fetched during this request, keyed by account.
     *
     * @var array<string, string>
     */
    private array $accessTokens = [];

    public function fields(): array
    {
        return [
            ['key' => 'client_id', 'label' => 'Client ID', 'hint' => 'From your PayPal app under Apps and Credentials.', 'secret' => false, 'required' => true],
            ['key' => 'client_secret', 'label' => 'Secret', 'hint' => 'Shown under the Client ID. Press "Show" to copy it.', 'secret' => true, 'required' => true],
        ];
    }

    public function steps(): array
    {
        return [
            'Sign in at developer.paypal.com with your PayPal business account.',
            'Open Apps and Credentials, make sure "Live" is selected, and press Create App.',
            'Copy the Client ID and the Secret.',
            'Paste them below and press Test and save.',
        ];
    }

    public function test(PaymentAccount $account): void
    {
        $this->accessToken($account);
    }

    public function startCheckout(PaymentAccount $account, PaidPlan $plan, string $email, string $reference, string $returnUrl, string $cancelUrl): string
    {
        $planId = $this->planId($account, $plan);

        $response = $this->send($account, fn (PendingRequest $http) => $http->post('v1/billing/subscriptions', [
            'plan_id' => $planId,
            'custom_id' => $reference,
            'subscriber' => ['email_address' => $email],
            'application_context' => [
                'brand_name' => Str::limit($account->newsletter->name, 120, ''),
                'user_action' => 'SUBSCRIBE_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ]));

        $approveUrl = collect($response->json('links', []))->firstWhere('rel', 'approve')['href'] ?? null;

        if (! is_string($approveUrl) || ! str_starts_with($approveUrl, 'https://')) {
            throw new PaymentException('PayPal did not start the checkout.', 'Try again in a few minutes.');
        }

        return $approveUrl;
    }

    public function confirmCheckout(PaymentAccount $account, Request $request, string $reference): PaidAccess
    {
        $subscriptionId = (string) $request->query('subscription_id');

        if (! preg_match('/^I-[A-Z0-9]+$/', $subscriptionId)) {
            throw PaymentException::notCompleted(PaymentProvider::PayPal);
        }

        $subscription = $this->send($account, fn (PendingRequest $http) => $http->get('v1/billing/subscriptions/'.$subscriptionId))->json();

        // APPROVED turns into ACTIVE moments after the reader returns, once PayPal takes the first payment.
        if (($subscription['custom_id'] ?? null) !== $reference || ! in_array($subscription['status'] ?? null, ['ACTIVE', 'APPROVED'], true)) {
            throw PaymentException::notCompleted(PaymentProvider::PayPal);
        }

        $planKey = array_search($subscription['plan_id'] ?? null, $account->detail('plans', []), true);
        $plan = PaidPlan::tryFrom(strtok((string) $planKey, ':')) ?? PaidPlan::Monthly;

        return new PaidAccess($subscriptionId, $this->nextBillingTime($subscription) ?? $plan->after(now()), $plan);
    }

    public function paidUntil(PaymentAccount $account, string $subscriptionId): ?CarbonInterface
    {
        $subscription = $this->send($account, fn (PendingRequest $http) => $http->get('v1/billing/subscriptions/'.rawurlencode($subscriptionId)))->json();

        return ($subscription['status'] ?? null) === 'ACTIVE' ? $this->nextBillingTime($subscription) : null;
    }

    protected function provider(): PaymentProvider
    {
        return PaymentProvider::PayPal;
    }

    protected function client(PaymentAccount $account): PendingRequest
    {
        return $this->http()
            ->asJson()
            ->baseUrl($this->baseUrl($account))
            ->withToken($this->accessToken($account));
    }

    /**
     * @throws PaymentException
     */
    private function accessToken(PaymentAccount $account): string
    {
        $clientId = $this->required($account, 'client_id', 'Client ID');
        $secret = $this->required($account, 'client_secret', 'Secret');
        $cacheKey = $this->baseUrl($account).'|'.$clientId.'|'.hash('sha256', $secret);

        if (isset($this->accessTokens[$cacheKey])) {
            return $this->accessTokens[$cacheKey];
        }

        try {
            $response = $this->http()
                ->asForm()
                ->withBasicAuth($clientId, $secret)
                ->post($this->baseUrl($account).'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException $exception) {
            throw PaymentException::fromConnectionError(PaymentProvider::PayPal, $exception);
        }

        // Wrong keys come back as 401, or as 400 with "invalid_client".
        if ($response->status() === 401 || $response->json('error') === 'invalid_client') {
            throw new PaymentException(
                'PayPal did not accept your keys.',
                'Copy the Client ID and Secret again from your PayPal app. Make sure they are the Live keys, not the Sandbox ones.',
            );
        }

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw PaymentException::fromResponse(PaymentProvider::PayPal, $response);
        }

        return $this->accessTokens[$cacheKey] = $response->json('access_token');
    }

    /**
     * The billing plan for this plan at the newsletter's current price, created on first use.
     *
     * @throws PaymentException
     */
    private function planId(PaymentAccount $account, PaidPlan $plan): string
    {
        $newsletter = $account->newsletter;
        $cents = (int) $newsletter->paidPriceCents($plan);
        $currency = strtoupper($newsletter->paid_currency);
        $key = $plan->value.':'.$cents.':'.$currency;

        if ($planId = $account->detail('plans.'.$key)) {
            return $planId;
        }

        $productId = $account->detail('product_id') ?? $this->send($account, fn (PendingRequest $http) => $http->post('v1/catalogs/products', [
            'name' => Str::limit($newsletter->name.' paid subscription', 127, ''),
            'type' => 'SERVICE',
        ]))->json('id');

        $planId = $this->send($account, fn (PendingRequest $http) => $http->post('v1/billing/plans', [
            'product_id' => $productId,
            'name' => Str::limit($newsletter->name.' '.Str::lower($plan->label()), 127, ''),
            'status' => 'ACTIVE',
            'billing_cycles' => [[
                'frequency' => ['interval_unit' => strtoupper($plan->period()), 'interval_count' => 1],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0,
                'pricing_scheme' => ['fixed_price' => ['value' => number_format($cents / 100, 2, '.', ''), 'currency_code' => $currency]],
            ]],
            'payment_preferences' => ['auto_bill_outstanding' => true, 'payment_failure_threshold' => 1],
        ]))->json('id');

        $details = $account->details ?? [];
        $details['product_id'] = $productId;
        $details['plans'][$key] = $planId;
        $account->update(['details' => $details]);

        return $planId;
    }

    private function baseUrl(PaymentAccount $account): string
    {
        return $account->credential('mode') === 'sandbox' ? self::SANDBOX_URL : self::LIVE_URL;
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function nextBillingTime(array $subscription): ?CarbonInterface
    {
        $time = data_get($subscription, 'billing_info.next_billing_time');

        return is_string($time) ? Carbon::parse($time) : null;
    }
}
