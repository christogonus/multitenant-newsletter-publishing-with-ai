<?php

namespace App\Enums;

enum AiUsageKind: string
{
    case Research = 'research';
    case Issue = 'issue';
    case Topics = 'topics';
    case Social = 'social';
    case MediaKit = 'media_kit';
    case LeadPage = 'lead_page';

    public function label(): string
    {
        return match ($this) {
            self::Research => 'Research',
            self::Issue => 'Newsletter',
            self::Topics => 'Topics',
            self::Social => 'Social posts',
            self::MediaKit => 'Media kit',
            self::LeadPage => 'Sign-up page',
        };
    }

    /**
     * Whether this kind counts toward the weekly issue quota.
     */
    public function countsTowardIssueQuota(): bool
    {
        return $this === self::Issue;
    }
}
