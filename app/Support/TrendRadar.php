<?php

namespace App\Support;

use App\Models\Broadcast;
use Illuminate\Support\Carbon;

/**
 * The Newsletter Trend Radar bonus schedule: one edition a month, Edition 1 shipping with the bonus pack. Each later
 * edition counts as delivered once an admin sends its email, which carries the edition's tag.
 */
class TrendRadar
{
    /**
     * The edition whose month it is, or null before Edition 1's month and after the last edition's.
     */
    public function currentEdition(): ?int
    {
        $starts = Carbon::parse((string) config('newspub.trend_radar.starts'))->startOfMonth();

        if (now()->lt($starts)) {
            return null;
        }

        $edition = (int) $starts->diffInMonths(now()->startOfMonth()) + 1;

        return $edition <= (int) config('newspub.trend_radar.editions') ? $edition : null;
    }

    /**
     * This month's edition if its email has not gone out yet. Edition 1 ships with the bonus pack, so it is never due.
     */
    public function dueEdition(): ?int
    {
        $edition = $this->currentEdition();

        if ($edition === null || $edition < 2 || Broadcast::query()->where('tag', self::tag($edition))->exists()) {
            return null;
        }

        return $edition;
    }

    /**
     * The month an edition belongs to, such as "December 2026".
     */
    public function month(int $edition): string
    {
        return Carbon::parse((string) config('newspub.trend_radar.starts'))->startOfMonth()->addMonths($edition - 1)->format('F Y');
    }

    public static function tag(int $edition): string
    {
        return 'trend-radar-'.$edition;
    }
}
