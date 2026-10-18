<?php

namespace App\Enums;

/**
 * Product areas. Each has its own accent colour so users always know which part of the business they are in.
 */
enum Area: string
{
    case Create = 'create';
    case Publish = 'publish';
    case Grow = 'grow';
    case Earn = 'earn';
    case Social = 'social';
    case Automate = 'automate';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The design-token name used in CSS classes, e.g. bg-create, text-create-ink.
     */
    public function token(): string
    {
        return $this->value;
    }
}
