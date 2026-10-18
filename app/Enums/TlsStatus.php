<?php

namespace App\Enums;

/**
 * The secure-connection (https certificate) state of a custom domain.
 */
enum TlsStatus: string
{
    case Pending = 'pending';
    case Requested = 'requested';
    case Active = 'active';
    case Failed = 'failed';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for your address to be connected',
            self::Requested => 'Setting up',
            self::Active => 'Secure',
            self::Failed => 'Not secure yet',
            self::External => 'Handled by your server',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Requested => 'brand',
            self::Failed => 'danger',
            self::Pending, self::External => 'neutral',
        };
    }
}
