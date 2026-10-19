<?php

namespace App\Integrations\Payments;

use App\Enums\PaymentProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * A Stripe or PayPal error with a plain-English fix the owner (or reader) can act on.
 */
class PaymentException extends RuntimeException
{
    public function __construct(string $message, public readonly string $fix = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(PaymentProvider $provider, Response $response): self
    {
        $name = $provider->label();
        $status = $response->status();

        return match (true) {
            $status === 401 => new self(
                "{$name} did not accept your keys.",
                "Copy the keys from {$name} again and paste them in. Make sure there are no spaces at the start or end.",
            ),
            $status === 403 => new self(
                "{$name} says these keys are not allowed to do this.",
                "Use a full secret key (not a restricted one), or check the permissions on your {$name} app.",
            ),
            $status === 400 || $status === 422 => new self(
                "{$name} did not accept the details we sent.",
                self::detail($response) ?: "Check your prices and your {$name} account settings, then try again.",
            ),
            default => new self(
                "{$name} is not answering properly right now (code {$status}).",
                'Try again in a few minutes.',
            ),
        };
    }

    public static function fromConnectionError(PaymentProvider $provider, ConnectionException $exception): self
    {
        return new self("We could not reach {$provider->label()}.", 'Try again in a few minutes.', $exception);
    }

    /**
     * The payment came back but does not belong to this reader, or was not completed.
     */
    public static function notCompleted(PaymentProvider $provider): self
    {
        return new self(
            "We could not confirm your {$provider->label()} payment.",
            'If you were charged, sign in again in a few minutes. Otherwise, please try subscribing again.',
        );
    }

    private static function detail(Response $response): ?string
    {
        $message = $response->json('error.message') ?? $response->json('details.0.description') ?? $response->json('message');

        return is_string($message) ? mb_substr(strip_tags($message), 0, 300) : null;
    }
}
