<?php

use App\Ai\Agents\LeadPageWriter;
use App\Ai\Agents\MediaKitWriter;
use App\Enums\AiUsageKind;
use App\Enums\Product;
use App\Livewire\Growth\Index as GrowthIndex;
use App\Livewire\Sponsors\Index as SponsorsIndex;
use App\Models\AiUsage;
use App\Models\LeadPage;
use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Support\PublicLinks;
use App\Support\RateGuide;
use Database\Seeders\SponsorSeeder;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

function leadPageOutput(): array
{
    return [
        'headline' => 'Get the 7-day budget reset',
        'subheadline' => 'A free checklist for a calmer month.',
        'bullets' => ['Find where money leaks', 'Set one simple rule'],
        'button_label' => 'Send me the checklist',
        'magnet_title' => '7-Day Budget Reset',
        'magnet_sections' => [
            ['heading' => 'Day 1: Look back', 'body' => "List last month's spending.\n\nCircle the surprises."],
            ['heading' => 'Day 2: One rule', 'body' => 'Pick one rule you can keep.'],
        ],
    ];
}

test('growth buyers create an AI sign-up page and readers who join get the gift', function () {
    LeadPageWriter::fake([leadPageOutput()]);
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto3Growth))->create();
    $this->actingAs($newsletter->user);

    Livewire::test(GrowthIndex::class)->set('giftIdea', 'budget checklist')->call('createLeadPage')->assertRedirect();

    $leadPage = LeadPage::sole();
    expect($leadPage->slug)->toBe('7-day-budget-reset')
        ->and(collect($leadPage->magnet_blocks)->pluck('type')->all())->toBe(['heading', 'paragraph', 'paragraph', 'heading', 'paragraph'])
        ->and(AiUsage::where('kind', AiUsageKind::LeadPage)->count())->toBe(1);

    auth()->logout();

    $this->get(publicationUrl($newsletter, 'join/'.$leadPage->slug))->assertOk()->assertSee('Get the 7-day budget reset');

    $response = $this->post(publicationUrl($newsletter, 'join/'.$leadPage->slug), ['email' => 'fan@example.com', 'name' => 'Fan']);
    $response->assertRedirect();

    $subscriber = Subscriber::sole();
    expect($subscriber->source)->toBe('lead_page')->and($subscriber->lead_page_id)->toBe($leadPage->id);

    $this->get($response->headers->get('Location'))->assertOk()->assertSee('Day 1: Look back');
});

test('lead pages disappear when the growth upgrade is refunded', function () {
    $newsletter = Newsletter::factory()->create();
    $leadPage = $newsletter->leadPages()->create(['slug' => 'gift', 'headline' => 'Gift', 'magnet_title' => 'Gift']);

    $this->get(publicationUrl($newsletter, 'join/'.$leadPage->slug))->assertNotFound();
});

test('readers who sign up through a referral link are credited to the referrer', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto3Growth))->create([
        'reward_tiers' => [['referrals' => 1, 'reward' => 'A shout-out']],
    ]);
    $referrer = Subscriber::factory()->for($newsletter)->create();

    $this->get(publicationUrl($newsletter, 'r/'.$referrer->referral_code))
        ->assertRedirect()
        ->assertCookie('np_ref_'.$newsletter->id, $referrer->referral_code);

    $this->post(publicationUrl($newsletter, 'subscribe'), ['email' => 'friend@example.com', 'ref' => $referrer->referral_code]);

    $friend = Subscriber::where('email', 'friend@example.com')->sole();
    expect($friend->referred_by_id)->toBe($referrer->id);

    $this->get(publicationUrl($newsletter, 'thanks'))->assertOk()->assertSee('earn rewards')->assertSee('r/'.$friend->referral_code);

    $status = URL::signedRoute('public.referrals', ['publication' => $newsletter->subdomain, 'subscriber' => $referrer->id]);
    $this->get($status)->assertOk()->assertSee('Unlocked');
});

test('a referral code from another publication is ignored', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto3Growth))->create();
    $outsider = Subscriber::factory()->create();

    $this->post(publicationUrl($newsletter, 'subscribe'), ['email' => 'friend@example.com', 'ref' => $outsider->referral_code]);

    expect(Subscriber::where('email', 'friend@example.com')->sole()->referred_by_id)->toBeNull();
});

test('reward tiers are validated and saved in order', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto3Growth))->create();
    $this->actingAs($newsletter->user);

    Livewire::test(GrowthIndex::class)
        ->set('tiers', [['referrals' => 10, 'reward' => 'Guide'], ['referrals' => 2, 'reward' => 'Shout-out']])
        ->call('saveTiers')
        ->assertHasNoErrors();

    expect($newsletter->fresh()->reward_tiers)->toBe([['referrals' => 2, 'reward' => 'Shout-out'], ['referrals' => 10, 'reward' => 'Guide']]);

    Livewire::test(GrowthIndex::class)->set('tiers', [['referrals' => '', 'reward' => '']])->call('saveTiers')->assertHasErrors(['tiers.0.referrals', 'tiers.0.reward']);
});

test('sponsor buyers get an honest AI media kit from their real numbers', function () {
    MediaKitWriter::fake([[
        'headline' => 'Reach 3 focused readers',
        'about' => 'About us',
        'audience' => 'Beginners',
        'why_sponsor' => ['Engaged'],
        'packages' => [['name' => 'Main sponsor', 'description' => 'Top slot', 'price' => '$25']],
    ]]);
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto4Sponsor))->create();
    Subscriber::factory()->for($newsletter)->count(3)->create();
    $this->actingAs($newsletter->user);

    Livewire::test(SponsorsIndex::class)->call('generateMediaKit')->assertSet('tab', 'media-kit')->assertSee('Reach 3 focused readers');

    MediaKitWriter::assertPrompted(fn ($prompt) => $prompt->contains('Active readers: 3'));
    expect($newsletter->mediaKit->content['packages'][0]['price'])->toBe('$25');
});

test('the rate guide scales with readers and never goes below a floor', function () {
    $guide = new RateGuide;

    expect($guide->packages(0)[1]['amount'])->toBe(25)
        ->and($guide->packages(10_000)[1]['amount'])->toBeGreaterThan($guide->packages(1_000)[1]['amount']);
});

test('the sponsor directory lists seeded entries', function () {
    $this->seed(SponsorSeeder::class);
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto4Sponsor))->create();

    $this->actingAs($newsletter->user)->get(route('sponsors.index'))->assertOk()->assertSee('Paved')->assertSee('Check each one');
});

test('public link helpers build referral links on the publication host', function () {
    $newsletter = Newsletter::factory()->create();
    $subscriber = Subscriber::factory()->for($newsletter)->create();

    expect(PublicLinks::referralShareUrl($newsletter, $subscriber))->toBe($newsletter->publicUrl('r/'.$subscriber->referral_code));
});
