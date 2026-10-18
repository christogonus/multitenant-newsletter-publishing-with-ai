<?php

namespace App\Enums;

/**
 * Every sellable product. Each case is one Pennant feature and one row group in the purchases ledger.
 */
enum Product: string
{
    case FrontEnd = 'front_end';
    case BumpVault = 'bump_vault';
    case Oto1Lite = 'oto1_lite';
    case Oto1Pro = 'oto1_pro';
    case Oto2Unlimited = 'oto2_unlimited';
    case Oto3Growth = 'oto3_growth';
    case Oto4Sponsor = 'oto4_sponsor';
    case Oto5Dfy = 'oto5_dfy';
    case Oto6Social = 'oto6_social';
    case Oto7Autopilot = 'oto7_autopilot';
    case Oto8Premium = 'oto8_premium';
    case Oto9Multi = 'oto9_multi';
    case Oto10Network = 'oto10_network';
    case Oto11Agency = 'oto11_agency';
    case Oto12Reseller = 'oto12_reseller';

    public function label(): string
    {
        return match ($this) {
            self::FrontEnd => 'NewsPub AI',
            self::BumpVault => 'Niche and Swipe Vault',
            self::Oto1Lite => 'Delivery and Integrations Lite',
            self::Oto1Pro => 'Delivery and Integrations Pro',
            self::Oto2Unlimited => 'Unlimited Creation',
            self::Oto3Growth => 'Subscriber Growth Engine',
            self::Oto4Sponsor => 'Sponsor and Payday Vault',
            self::Oto5Dfy => 'Done-For-You Empire',
            self::Oto6Social => 'Social Repurposing',
            self::Oto7Autopilot => 'Content Autopilot',
            self::Oto8Premium => 'Premium Designs and Domain',
            self::Oto9Multi => 'Multi-Newsletter Manager',
            self::Oto10Network => 'Cross-Promo Network',
            self::Oto11Agency => 'Agency Client License',
            self::Oto12Reseller => 'Reseller License',
        };
    }

    /**
     * One-line benefit shown on locked-feature cards.
     */
    public function benefit(): string
    {
        return match ($this) {
            self::FrontEnd => 'Write, publish and grow a newsletter with AI.',
            self::BumpVault => 'Ranked niches, proven subject lines and a 30-day launch plan.',
            self::Oto1Lite => 'Send your issues through one email service you already use.',
            self::Oto1Pro => 'Connect every email service you use and send on a schedule.',
            self::Oto2Unlimited => 'Write as many issues as you like, with faster writing.',
            self::Oto3Growth => 'Sign-up pages, free gifts and reader referrals that bring new readers.',
            self::Oto4Sponsor => 'Find sponsors, build a media kit and know what to charge.',
            self::Oto5Dfy => 'Install ready-made newsletters with topics and first issues done.',
            self::Oto6Social => 'Turn each issue into posts and video scripts in one click.',
            self::Oto7Autopilot => 'Plan a month of issues and let them go out on their own.',
            self::Oto8Premium => 'Premium designs, your logo and colours, and your own web address.',
            self::Oto9Multi => 'Run as many newsletters as you like from one place.',
            self::Oto10Network => 'Swap recommendations with other newsletters to grow together.',
            self::Oto11Agency => 'Run newsletters for paying clients with ready packages and contracts.',
            self::Oto12Reseller => 'Sell NewsPub AI accounts and keep the profit.',
        };
    }

    public function price(): string
    {
        return match ($this) {
            self::FrontEnd, self::BumpVault => '$17',
            self::Oto1Lite => '$67',
            self::Oto1Pro, self::Oto2Unlimited, self::Oto3Growth, self::Oto4Sponsor, self::Oto9Multi, self::Oto10Network => '$97',
            self::Oto5Dfy => '$127',
            self::Oto6Social, self::Oto7Autopilot, self::Oto8Premium => '$67',
            self::Oto11Agency => '$197',
            self::Oto12Reseller => '$297',
        };
    }

    public function area(): Area
    {
        return match ($this) {
            self::FrontEnd, self::BumpVault, self::Oto5Dfy, self::Oto8Premium => Area::Create,
            self::Oto1Lite, self::Oto1Pro => Area::Publish,
            self::Oto3Growth, self::Oto10Network => Area::Grow,
            self::Oto4Sponsor, self::Oto11Agency, self::Oto12Reseller => Area::Earn,
            self::Oto6Social => Area::Social,
            self::Oto2Unlimited, self::Oto7Autopilot, self::Oto9Multi => Area::Automate,
        };
    }

    public function checkoutUrl(): string
    {
        return (string) config("products.products.{$this->value}.checkout_url", '#');
    }

    /**
     * LaunchpadJV IDs for this product, each "product_id" or "product_id:offer_id".
     *
     * @return list<string>
     */
    public function launchpadIds(): array
    {
        return array_values(array_filter((array) config("products.products.{$this->value}.launchpad_ids", [])));
    }

    /**
     * The product a LaunchpadJV sale belongs to. A "product_id:offer_id" mapping wins over a plain "product_id" one.
     */
    public static function fromLaunchpad(string $productId, ?string $offerId = null): ?self
    {
        $candidates = array_filter([$offerId !== null && $offerId !== '' ? $productId.':'.$offerId : null, $productId]);

        foreach ($candidates as $candidate) {
            foreach (self::cases() as $product) {
                if (in_array($candidate, $product->launchpadIds(), true)) {
                    return $product;
                }
            }
        }

        return null;
    }

    /**
     * Products whose ownership also grants this one (Pro includes Lite).
     *
     * @return list<self>
     */
    public function grantedBy(): array
    {
        return match ($this) {
            self::Oto1Lite => [self::Oto1Lite, self::Oto1Pro],
            default => [$this],
        };
    }
}
