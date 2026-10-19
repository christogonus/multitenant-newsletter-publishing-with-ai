<?php

namespace App\Integrations\Payments;

use App\Enums\PaymentProvider;
use App\Models\PaymentAccount;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shared HTTP plumbing for payment providers.
 */
abstract class HttpPaymentGateway implements PaymentGateway
{
    abstract protected function provider(): PaymentProvider;

    abstract protected function client(PaymentAccount $account): PendingRequest;

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(20)
            ->connectTimeout(10)
            ->withUserAgent('NewsPubAI/1.0')
            ->withOptions(['allow_redirects' => false]);
    }

    /**
     * Runs a request and converts failures into a PaymentException with a plain-English fix.
     *
     * @param  Closure(PendingRequest): Response  $request
     *
     * @throws PaymentException
     */
    protected function send(PaymentAccount $account, Closure $request): Response
    {
        try {
            $response = $request($this->client($account));
        } catch (ConnectionException $exception) {
            throw PaymentException::fromConnectionError($this->provider(), $exception);
        }

        if ($response->failed()) {
            throw PaymentException::fromResponse($this->provider(), $response);
        }

        return $response;
    }

    /**
     * @throws PaymentException
     */
    protected function required(PaymentAccount $account, string $key, string $label): string
    {
        $value = trim((string) $account->credential($key));

        if ($value === '') {
            throw new PaymentException("Your {$this->provider()->label()} connection is missing the {$label}.", "Open Paid subscriptions and fill in the {$label}.");
        }

        return $value;
    }
}
