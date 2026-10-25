<?php

namespace App\Support;

use App\Enums\Area;
use App\Enums\Product;
use App\Models\User;

/**
 * The dashboard sidebar. Locked areas stay visible and tinted so buyers can see what an upgrade adds.
 */
class Navigation
{
    public function __construct(private Entitlements $entitlements) {}

    /**
     * @return list<array{area: ?Area, label: string, items: list<array{label: string, route: string, icon: string, locked: bool, active: string}>}>
     */
    public function sections(User $user): array
    {
        $item = fn (string $label, string $route, string $icon, ?Product $product = null, ?string $active = null) => [
            'label' => $label,
            'route' => $route,
            'icon' => $icon,
            'locked' => $product !== null && ! $this->entitlements->has($user, $product),
            'active' => $active ?? $route,
        ];

        return [
            [
                'area' => null,
                'label' => '',
                'items' => [
                    $item('Home', 'dashboard', 'home'),
                    ...($user->purchases()->standing()->whereNotNull('bump_offer_id')->exists() ? [$item('Downloads', 'downloads.index', 'download')] : []),
                ],
            ],
            [
                'area' => Area::Create,
                'label' => 'Create',
                'items' => [
                    $item('Newsletters', 'issues.index', 'newspaper', active: 'issues.*'),
                    $item('Design', 'design.index', 'swatch'),
                    $item('Ready-made kits', 'kits.index', 'stack', Product::Oto5Dfy),
                    $item('Niche vault', 'vault.index', 'archive', Product::BumpVault),
                ],
            ],
            [
                'area' => Area::Publish,
                'label' => 'Publish',
                'items' => [
                    $item('Readers', 'subscribers.index', 'users'),
                    $item('Email services', 'integrations.index', 'envelope', Product::Oto1Delivery),
                    $item('Publication settings', 'newsletters.settings', 'adjustments'),
                ],
            ],
            [
                'area' => Area::Grow,
                'label' => 'Grow',
                'items' => [
                    $item('Sign-up pages', 'growth.index', 'gift', Product::Oto3Growth, 'growth.*'),
                    $item('Cross-promo network', 'network.index', 'swap', Product::Oto10Network),
                ],
            ],
            [
                'area' => Area::Earn,
                'label' => 'Earn',
                'items' => [
                    $item('Sponsors', 'sponsors.index', 'currency-dollar', Product::Oto4Sponsor),
                    $item('Agency clients', 'agency.index', 'briefcase', Product::Oto11Agency),
                    $item('Reseller', 'reseller.index', 'ticket', Product::Oto12Reseller),
                ],
            ],
            [
                'area' => Area::Social,
                'label' => 'Social',
                'items' => [
                    $item('Social posts', 'social.index', 'share', Product::Oto6Social),
                ],
            ],
            [
                'area' => Area::Automate,
                'label' => 'Automate',
                'items' => [
                    $item('Autopilot calendar', 'autopilot.index', 'calendar', Product::Oto7Autopilot),
                    $item('All publications', 'publications.index', 'squares', Product::Oto9Multi),
                ],
            ],
        ];
    }
}
