<?php

namespace App\Support;

/**
 * Suggested sponsorship prices from audience size. Starting points only; owners set their own prices.
 */
class RateGuide
{
    public const OPEN_RATE = 0.45;

    public const CPM_LOW = 25;

    public const CPM_HIGH = 50;

    /**
     * @return list<array{name: string, description: string, price: string, amount: int}>
     */
    public function packages(int $subscribers): array
    {
        $opens = max(1, (int) round($subscribers * self::OPEN_RATE));
        $main = $this->roundPrice(max(25, $opens / 1000 * (self::CPM_LOW + self::CPM_HIGH) / 2));

        return [
            ['name' => 'Classified', 'description' => 'A short text mention near the end of one issue.', 'amount' => $classified = $this->roundPrice(max(10, $main * 0.3)), 'price' => '$'.$classified],
            ['name' => 'Main sponsor', 'description' => 'Logo and a short paragraph at the top of one issue.', 'amount' => $main, 'price' => '$'.$main],
            ['name' => 'Dedicated email', 'description' => 'A whole issue written about the sponsor, clearly labelled.', 'amount' => $dedicated = $this->roundPrice($main * 2.5), 'price' => '$'.$dedicated],
            ['name' => 'Monthly bundle', 'description' => 'Main sponsor slot in four issues, 15% off.', 'amount' => $bundle = $this->roundPrice($main * 4 * 0.85), 'price' => '$'.$bundle],
        ];
    }

    /**
     * @return array{opens: int, cpm_low: int, cpm_high: int}
     */
    public function assumptions(int $subscribers): array
    {
        return [
            'opens' => (int) round($subscribers * self::OPEN_RATE),
            'cpm_low' => self::CPM_LOW,
            'cpm_high' => self::CPM_HIGH,
        ];
    }

    private function roundPrice(float $amount): int
    {
        return (int) (ceil($amount / 5) * 5);
    }
}
