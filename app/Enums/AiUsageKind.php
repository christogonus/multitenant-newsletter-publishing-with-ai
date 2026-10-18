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

    /**
     * Whether this kind counts toward the weekly issue quota.
     */
    public function countsTowardIssueQuota(): bool
    {
        return $this === self::Issue;
    }
}
