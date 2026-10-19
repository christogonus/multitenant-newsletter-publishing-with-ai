<?php

namespace App\Integrations\Payments;

use App\Enums\PaidPlan;
use App\Models\PaymentAccount;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * Takes recurring payments from readers through the owner's own payment account.
 */
interface PaymentGateway
{
    /**
     * The keys the owner pastes in.
     *
     * @return list<array{key: string, label: string, hint: string, secret: bool, required: bool}>
     */
    public function fields(): array;

    /**
     * Plain-English steps for finding those keys.
     *
     * @return list<string>
     */
    public function steps(): array;

    /**
     * @throws PaymentException
     */
    public function test(PaymentAccount $account): void;

    /**
     * Starts a subscription checkout and returns the address to send the reader to.
     *
     * @param  string  $reference  Ties the checkout to this reader, so the return can be checked.
     *
     * @throws PaymentException
     */
    public function startCheckout(PaymentAccount $account, PaidPlan $plan, string $email, string $reference, string $returnUrl, string $cancelUrl): string;

    /**
     * Confirms the subscription the reader just paid for, from the provider's return request.
     *
     * @throws PaymentException
     */
    public function confirmCheckout(PaymentAccount $account, Request $request, string $reference): PaidAccess;

    /**
     * When the subscription's current paid period ends, or null if it is no longer active.
     *
     * @throws PaymentException
     */
    public function paidUntil(PaymentAccount $account, string $subscriptionId): ?CarbonInterface;
}
