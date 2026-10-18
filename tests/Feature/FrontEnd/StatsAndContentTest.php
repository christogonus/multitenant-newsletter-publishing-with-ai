<?php

use App\Ai\Agents\MediaKitWriter;
use App\Enums\IssueStatus;
use App\Enums\Product;
use App\Enums\SwapStatus;
use App\Livewire\Admin\Content;
use App\Livewire\Issues\Editor;
use App\Livewire\Issues\Index as Issues;
use App\Livewire\Sponsors\Index as Sponsors;
use App\Models\Issue;
use App\Models\NetworkListing;
use App\Models\NetworkSwap;
use App\Models\Newsletter;
use App\Models\Sponsor;
use App\Models\Subscriber;
use App\Models\User;
use App\Support\Entitlements;
use Database\Seeders\NetworkSeeder;
use Database\Seeders\SponsorSeeder;
use Livewire\Livewire;

test('reader views of an issue are counted, but bots and previews are not', function () {
    $newsletter = Newsletter::factory()->create();
    $issue = Issue::factory()->for($newsletter)->published()->create();
    $updatedAt = $issue->updated_at;

    $this->get(publicationUrl($newsletter, 'issues/'.$issue->public_slug), ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0)'])->assertOk();
    $this->get(publicationUrl($newsletter, 'issues/'.$issue->public_slug), ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0)'])->assertOk();
    $this->get(publicationUrl($newsletter, 'issues/'.$issue->public_slug), ['User-Agent' => 'Googlebot/2.1'])->assertOk();
    $this->get(appUrl('p/'.$newsletter->subdomain.'/issues/'.$issue->public_slug), ['User-Agent' => 'Mozilla/5.0'])->assertOk();

    $issue->refresh();
    expect($issue->views)->toBe(2)->and($issue->updated_at->equalTo($updatedAt))->toBeTrue();
});

test('issues can be searched by title or subject line', function () {
    $newsletter = Newsletter::factory()->create();
    Issue::factory()->for($newsletter)->create(['title' => 'Grocery savings', 'subject_line' => 'Cheaper food']);
    Issue::factory()->for($newsletter)->create(['title' => 'Holiday budget', 'subject_line' => 'Travel for less']);
    $this->actingAs($newsletter->user);

    Livewire::test(Issues::class)
        ->set('search', 'grocery')->assertSee('Grocery savings')->assertDontSee('Holiday budget')
        ->set('search', 'travel')->assertSee('Holiday budget')->assertDontSee('Grocery savings');
});

test('duplicating an issue makes a manual draft that does not use the quota', function () {
    $issue = Issue::factory()->published()->create(['title' => 'Original']);
    $this->actingAs($issue->newsletter->user);

    Livewire::test(Editor::class, ['issue' => $issue])->call('duplicate')->assertRedirect();

    $copy = Issue::where('title', 'Copy of Original')->sole();
    expect($copy->status)->toBe(IssueStatus::Draft)
        ->and($copy->is_ai_generated)->toBeFalse()
        ->and($copy->public_slug)->toBeNull()
        ->and(collect($copy->blocks)->pluck('id')->intersect(collect($issue->blocks)->pluck('id')))->toBeEmpty()
        ->and(app(Entitlements::class)->issuesUsedThisWeek($issue->newsletter->user))->toBe(1);
});

test('the readers page shows daily sign-ups and where readers came from', function () {
    $newsletter = Newsletter::factory()->create();
    Subscriber::factory()->for($newsletter)->count(3)->create(['source' => 'hosted_page']);
    Subscriber::factory()->for($newsletter)->create(['source' => 'lead_page', 'created_at' => now()->subDays(3)]);

    $this->actingAs($newsletter->user)
        ->get(route('subscribers.index'))
        ->assertOk()
        ->assertSee('New readers per day')
        ->assertSee('Your page')
        ->assertSee('Sign-up pages');
});

test('the media kit is given the average views per issue', function () {
    MediaKitWriter::fake([['headline' => 'h', 'about' => 'a', 'audience' => 'x', 'why_sponsor' => [], 'packages' => []]]);
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto4Sponsor))->create();
    Issue::factory()->for($newsletter)->published()->create(['views' => 100]);
    Issue::factory()->for($newsletter)->published()->create(['views' => 50]);
    $this->actingAs($newsletter->user);

    Livewire::test(Sponsors::class)->call('generateMediaKit');

    MediaKitWriter::assertPrompted(fn ($prompt) => $prompt->contains('Average web views per issue: 75'));
});

test('admins check, edit, add and hide sponsor entries', function () {
    $this->seed(SponsorSeeder::class);
    $admin = User::factory()->admin()->create();
    $entry = Sponsor::where('name', 'Paved')->sole();
    $this->actingAs($admin);

    Livewire::test(Content::class)
        ->assertSee('not checked yet')
        ->call('markChecked', $entry->id)
        ->call('toggleSponsor', $entry->id)
        ->call('editSponsor')
        ->set('sponsor.name', 'New Network')
        ->set('sponsor.kind', 'Ad network')
        ->set('sponsor.website', 'https://new.example')
        ->set('sponsor.description', 'A real network')
        ->call('saveSponsor')
        ->assertHasNoErrors();

    $entry->refresh();
    expect($entry->verified_at)->not->toBeNull()
        ->and($entry->is_active)->toBeFalse()
        ->and(Sponsor::where('name', 'New Network')->sole()->verified_at)->not->toBeNull();

    $this->actingAs(Newsletter::factory()->for(buyer(Product::Oto4Sponsor))->create()->user)
        ->get(route('sponsors.index'))
        ->assertDontSee('Paved')
        ->assertSee('New Network');
});

test('admins edit and hide featured network listings', function () {
    $this->seed(NetworkSeeder::class);
    $admin = User::factory()->admin()->create();
    $listing = NetworkListing::where('is_seeded', true)->first();
    $this->actingAs($admin);

    Livewire::test(Content::class)
        ->set('tab', 'network')
        ->call('editListing', $listing->id)
        ->set('listingPitch', 'Updated pitch')
        ->call('saveListing')
        ->call('toggleListing', $listing->id);

    $listing->refresh();
    expect($listing->pitch)->toBe('Updated pitch')->and($listing->is_listed)->toBeFalse();
});

test('buyers cannot use the content admin', function () {
    $this->actingAs(buyer())->get(route('admin.content'))->assertForbidden();

    $sponsor = Sponsor::create(['name' => 'X', 'kind' => 'Ad network', 'website' => 'https://x.example', 'description' => 'x']);
    Livewire::actingAs(buyer())->test(Content::class)->call('toggleSponsor', $sponsor->id)->assertForbidden();
});

test('lists that link to public pages load custom domains up front', function () {
    $user = buyer(Product::Oto9Multi, Product::Oto10Network);
    [$first, $second] = Newsletter::factory()->for($user)->count(2)->create();
    $mine = $first->networkListing()->create(['category' => 'tech', 'pitch' => 'mine']);

    foreach (range(1, 2) as $index) {
        $partner = Newsletter::factory()->create(['name' => "Partner {$index}"]);
        $listing = $partner->networkListing()->create(['category' => 'tech', 'pitch' => "p{$index}"]);
        NetworkSwap::create(['requester_listing_id' => $mine->id, 'recipient_listing_id' => $listing->id, 'status' => SwapStatus::Accepted]);
    }

    $this->actingAs($user)->get(route('publications.index'))->assertOk();
    $this->get(publicationUrl($first))->assertOk()->assertSee('Partner 1')->assertSee('Partner 2');
});
