<?php

namespace App\Integrations\Certificates;

use RuntimeException;

/**
 * A certificate step failed. The message is plain language and safe to show the publication owner.
 */
class CertificateException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
