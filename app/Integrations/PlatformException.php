<?php

namespace App\Integrations;

use App\Enums\Platform;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * A platform error with a plain-English fix the owner can act on.
 */
class PlatformException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $fix,
        public readonly bool $retryable = false,
        public readonly bool $credentialsInvalid = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The table that turns provider errors into plain-English fixes.
     */
    public static function fromResponse(Platform $platform, Response $response): self
    {
        $name = $platform->label();
        $status = $response->status();

        return match (true) {
            $status === 401 => new self(
                "{$name} did not accept your key.",
                "Copy the key from {$name} again and paste it in. Make sure there are no spaces at the start or end.",
                credentialsInvalid: true,
            ),
            $status === 403 => new self(
                "{$name} says your plan does not allow this.",
                "Some {$name} plans do not let other apps create posts or add readers. Check your {$name} plan, or use Download email file instead.",
                credentialsInvalid: true,
            ),
            $status === 404 => new self(
                "{$name} could not find that list or publication.",
                "Check the ID you pasted for your {$name} list or publication. It must belong to the same account as the key.",
                credentialsInvalid: true,
            ),
            $status === 422 || $status === 400 => new self(
                "{$name} did not accept the details we sent.",
                self::detail($response) ?: "Check your {$name} settings, such as the sender email, and try again.",
            ),
            $status === 429 => new self(
                "{$name} asked us to slow down.",
                'Nothing to do. We will try again in a few minutes.',
                retryable: true,
            ),
            $status >= 500 => new self(
                "{$name} is having problems right now.",
                'Nothing to do. We will try again in a few minutes.',
                retryable: true,
            ),
            default => new self(
                "{$name} returned an unexpected answer (code {$status}).",
                'Try again in a few minutes. If it keeps happening, contact support.',
                retryable: true,
            ),
        };
    }

    public static function fromConnectionError(Platform $platform, ConnectionException $exception): self
    {
        return new self(
            "We could not reach {$platform->label()}.",
            'Check the web address you entered. If it is right, we will try again in a few minutes.',
            retryable: true,
            previous: $exception,
        );
    }

    public static function missing(Platform $platform, string $field): self
    {
        return new self(
            "Your {$platform->label()} connection is missing the {$field}.",
            "Open Email services and fill in the {$field}.",
            credentialsInvalid: true,
        );
    }

    private static function detail(Response $response): ?string
    {
        $message = $response->json('detail') ?? $response->json('message') ?? $response->json('errors.0.message') ?? $response->json('errors.0');

        return is_string($message) ? mb_substr(strip_tags($message), 0, 300) : null;
    }
}
