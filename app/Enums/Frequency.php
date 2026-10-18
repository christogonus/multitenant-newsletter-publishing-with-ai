<?php

namespace App\Enums;

enum Frequency: string
{
    case Daily = 'daily';
    case ThreeTimesWeekly = 'three_weekly';
    case Weekly = 'weekly';
    case Fortnightly = 'fortnightly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Every day',
            self::ThreeTimesWeekly => '3 times a week',
            self::Weekly => 'Once a week',
            self::Fortnightly => 'Every 2 weeks',
        };
    }

    /**
     * Days between issues, used by the content calendar.
     */
    public function intervalDays(): float
    {
        return match ($this) {
            self::Daily => 1,
            self::ThreeTimesWeekly => 7 / 3,
            self::Weekly => 7,
            self::Fortnightly => 14,
        };
    }
}
