<?php

namespace App\Enums;

enum IssueStatus: string
{
    case Generating = 'generating';
    case Draft = 'draft';
    case Failed = 'failed';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Generating => 'Writing',
            self::Draft => 'Draft',
            self::Failed => 'Needs a retry',
            self::Scheduled => 'Scheduled',
            self::Published => 'Live',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Generating => 'brand',
            self::Draft => 'neutral',
            self::Failed => 'danger',
            self::Scheduled => 'warning',
            self::Published => 'success',
        };
    }
}
