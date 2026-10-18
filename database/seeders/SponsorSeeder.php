<?php

namespace Database\Seeders;

use App\Models\Sponsor;
use Illuminate\Database\Seeder;

/**
 * Seeds the sponsor and ad-network directory. Entries are stored unverified: confirm each is real and active before launch.
 */
class SponsorSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            ['name' => 'beehiiv Ad Network', 'kind' => 'Ad network', 'website' => 'https://www.beehiiv.com', 'description' => 'Paid ad placements for newsletters hosted on beehiiv. Brands are matched to your audience and you approve each ad.', 'niches' => ['Any'], 'minimum_audience' => 'Check current requirements'],
            ['name' => 'Kit Sponsor Network', 'kind' => 'Ad network', 'website' => 'https://kit.com', 'description' => 'Sponsorships for creators who send with Kit. Kit finds sponsors and places their ads in your emails.', 'niches' => ['Any'], 'minimum_audience' => 'Check current requirements'],
            ['name' => 'Paved', 'kind' => 'Marketplace', 'website' => 'https://www.paved.com', 'description' => 'A marketplace where brands book sponsorships in newsletters. List your newsletter and set your prices.', 'niches' => ['Business', 'Tech', 'Finance', 'Marketing'], 'minimum_audience' => 'Varies'],
            ['name' => 'Passionfroot', 'kind' => 'Marketplace', 'website' => 'https://www.passionfroot.me', 'description' => 'A storefront for selling sponsorships. Brands browse your packages and book directly.', 'niches' => ['Any'], 'minimum_audience' => 'None stated'],
            ['name' => 'Sponsy', 'kind' => 'Tool', 'website' => 'https://getsponsy.com', 'description' => 'Software for managing sponsors: booking calendar, invoices and reports.', 'niches' => ['Any'], 'minimum_audience' => 'None'],
            ['name' => 'Letterhead', 'kind' => 'Ad network', 'website' => 'https://www.tryletterhead.com', 'description' => 'Newsletter advertising tools and a network that connects publishers with advertisers.', 'niches' => ['News', 'Local', 'Business'], 'minimum_audience' => 'Varies'],
            ['name' => 'Reletter', 'kind' => 'Directory', 'website' => 'https://reletter.com', 'description' => 'A searchable directory of newsletters and their sponsors. Useful for finding brands that already sponsor newsletters like yours.', 'niches' => ['Any'], 'minimum_audience' => 'None'],
            ['name' => 'Who Sponsors Stuff', 'kind' => 'Directory', 'website' => 'https://whosponsorsstuff.com', 'description' => 'A list of brands that sponsor newsletters and podcasts, with examples of their past placements.', 'niches' => ['Any'], 'minimum_audience' => 'None'],
            ['name' => 'Impact.com', 'kind' => 'Affiliate network', 'website' => 'https://impact.com', 'description' => 'Join brand partner programmes and earn a commission when readers buy through your links.', 'niches' => ['Any'], 'minimum_audience' => 'Approval per brand'],
            ['name' => 'PartnerStack', 'kind' => 'Affiliate network', 'website' => 'https://partnerstack.com', 'description' => 'Partner programmes for business software. A good fit for tech, marketing and small-business newsletters.', 'niches' => ['Tech', 'Business', 'Marketing'], 'minimum_audience' => 'Approval per brand'],
            ['name' => 'Amazon Associates', 'kind' => 'Affiliate network', 'website' => 'https://affiliate-program.amazon.com', 'description' => 'Earn a small commission on products you recommend. Check the programme rules about links in emails before using it.', 'niches' => ['Any'], 'minimum_audience' => 'None'],
            ['name' => 'CJ Affiliate', 'kind' => 'Affiliate network', 'website' => 'https://www.cj.com', 'description' => 'A large affiliate network with programmes from retail, travel and finance brands.', 'niches' => ['Retail', 'Travel', 'Finance'], 'minimum_audience' => 'Approval per brand'],
        ];

        foreach ($entries as $entry) {
            Sponsor::updateOrCreate(['name' => $entry['name']], [...$entry, 'verified_at' => null, 'is_active' => true]);
        }
    }
}
