<?php

namespace App\Enums;

enum Platform: string
{
    case Beehiiv = 'beehiiv';
    case Ghost = 'ghost';
    case Kit = 'kit';
    case Mailchimp = 'mailchimp';
    case MailerLite = 'mailerlite';
    case HtmlExport = 'html_export';

    public function label(): string
    {
        return match ($this) {
            self::Beehiiv => 'beehiiv',
            self::Ghost => 'Ghost',
            self::Kit => 'Kit',
            self::Mailchimp => 'Mailchimp',
            self::MailerLite => 'MailerLite',
            self::HtmlExport => 'Copy and paste (Substack and others)',
        };
    }

    /**
     * Whether a connection needs saved credentials.
     */
    public function needsCredentials(): bool
    {
        return $this !== self::HtmlExport;
    }
}
