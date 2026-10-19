<?php

/*
|--------------------------------------------------------------------------
| Sponsor and Payday Vault content (OTO 4)
|--------------------------------------------------------------------------
|
| The directory seed lives in database/seeders/SponsorSeeder.php and is
| stored unverified. Re-check every entry is real and active before launch.
|
*/

return [

    'outreach' => [
        [
            'title' => 'First pitch to a brand you already use',
            'subject' => 'Partnering with {newsletter}?',
            'body' => "Hi {name},\n\nI write {newsletter}, a {frequency} newsletter for {audience}. I mention {brand} to readers often because I use it myself.\n\nI'd love to make it official with a sponsored spot. {subscribers} readers get every issue, and they are exactly the people {brand} wants to reach.\n\nOur main sponsor spot is {price}. I can send our one-page media kit and two recent issues.\n\nWould you be open to a quick chat?\n\nThanks,\n{your_name}",
        ],
        [
            'title' => 'Follow-up after no reply',
            'subject' => 'Re: Partnering with {newsletter}?',
            'body' => "Hi {name},\n\nJust bringing this back to the top of your inbox. Happy to start with a single test issue so you can see the results before committing to more.\n\nHere is our media kit: {media_kit_link}\n\nThanks,\n{your_name}",
        ],
        [
            'title' => 'Pitch to a local business',
            'subject' => 'Reach {audience} in {town}',
            'body' => "Hi {name},\n\nI run {newsletter}, read by {subscribers} people in {town}. Local readers trust our recommendations and act on them.\n\nWe have one sponsor spot per issue. It includes your logo, a short paragraph and a link. The price is {price}.\n\nCould I send over a sample issue?\n\nThanks,\n{your_name}",
        ],
        [
            'title' => 'Thank-you and renewal after a sponsorship',
            'subject' => 'Results from your {newsletter} spot',
            'body' => "Hi {name},\n\nThanks again for sponsoring {newsletter}. Your spot went to {subscribers} readers and readers clicked your link {clicks} times.\n\nWould you like to book the next month? Sponsors who book four issues get 15% off.\n\nThanks,\n{your_name}",
        ],
    ],

    'paid_guide' => [
        ['title' => 'Decide what paying readers get', 'body' => 'Keep your main issue free so it keeps growing. Offer something extra for paying readers: a bonus issue each week, deeper guides, templates, or a private community.'],
        ['title' => 'Pick a fair price', 'body' => 'Most small newsletters charge between $5 and $10 a month, or $50 to $100 a year. Offer the yearly price at about two months free.'],
        ['title' => 'Connect where payments go', 'body' => 'Connect your Stripe or PayPal above (or both), and access starts and renews on its own. No business account? Use "Pay another way" for bank transfer or crypto and let readers in by hand. Then mark issues "Paying readers only" in the editor.'],
        ['title' => 'Launch to your best readers first', 'body' => 'Tell your most engaged readers first and offer a "founding member" discount for the first 30 days. Their feedback shapes the paid offer.'],
        ['title' => 'Keep paying readers happy', 'body' => 'Deliver the extra content on the same day every week. Ask paying readers once a quarter what they want next.'],
    ],

];
