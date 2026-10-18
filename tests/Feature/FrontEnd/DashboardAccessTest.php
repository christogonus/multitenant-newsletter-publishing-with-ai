<?php

use App\Enums\Product;
use App\Livewire\Issues\Editor;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Models\User;
use Livewire\Livewire;

test('guests are sent to sign in', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the sign-in page renders', function () {
    $this->get(route('login'))->assertOk()->assertSee('Welcome back');
});

test('a refunded buyer sees a locked screen, not an error', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('not active');
});

test('a buyer without a publication is sent to the start wizard', function () {
    $this->actingAs(buyer())->get(route('issues.index'))->assertRedirect(route('newsletters.create'));
});

test('every dashboard screen loads for a buyer who owns everything', function (string $routeName) {
    $user = buyer(...Product::cases());
    $newsletter = Newsletter::factory()->for($user)->create();
    Issue::factory()->for($newsletter)->published()->create();

    $this->actingAs($user)->get(route($routeName))->assertOk();
})->with([
    'dashboard', 'issues.index', 'issues.create', 'subscribers.index', 'newsletters.settings', 'design.index',
    'integrations.index', 'growth.index', 'sponsors.index', 'social.index', 'autopilot.index', 'network.index',
    'kits.index', 'publications.index', 'agency.index', 'reseller.index', 'vault.index', 'upgrades', 'support', 'account',
]);

test('locked areas show the upgrade card with the checkout link', function (string $routeName, Product $product) {
    configureWarriorPlus();
    $newsletter = Newsletter::factory()->create();

    $this->actingAs($newsletter->user)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee($product->label())
        ->assertSee('https://checkout.test/'.$product->value);
})->with([
    ['integrations.index', Product::Oto1Lite],
    ['growth.index', Product::Oto3Growth],
    ['sponsors.index', Product::Oto4Sponsor],
    ['kits.index', Product::Oto5Dfy],
    ['social.index', Product::Oto6Social],
    ['autopilot.index', Product::Oto7Autopilot],
    ['publications.index', Product::Oto9Multi],
    ['network.index', Product::Oto10Network],
    ['agency.index', Product::Oto11Agency],
    ['reseller.index', Product::Oto12Reseller],
    ['vault.index', Product::BumpVault],
]);

test('a buyer cannot open another buyer\'s issue', function () {
    $mine = Newsletter::factory()->create();
    $theirs = Issue::factory()->create();

    $this->actingAs($mine->user)->get(route('issues.edit', $theirs))->assertForbidden();
    $this->actingAs($mine->user)->get(route('issues.preview', $theirs))->assertForbidden();
});

test('the editor saves, reorders, adds and removes blocks', function () {
    $issue = Issue::factory()->create();
    $this->actingAs($issue->newsletter->user);
    [$first, $second] = $issue->blocks;

    $component = Livewire::test(Editor::class, ['issue' => $issue])
        ->call('moveBlock', $second['id'], 0)
        ->call('addBlock', 'quote')
        ->call('removeBlock', $first['id']);

    $types = collect($issue->fresh()->blocks)->pluck('type')->all();
    expect($types)->toBe(['heading', 'bullets', 'quote']);

    $component->set('subjectLine', 'A better subject')->assertHasNoErrors();
    expect($issue->fresh()->subject_line)->toBe('A better subject');
});

test('the editor refuses to publish an empty issue', function () {
    $issue = Issue::factory()->create(['blocks' => []]);
    $this->actingAs($issue->newsletter->user);

    Livewire::test(Editor::class, ['issue' => $issue])->call('publish')->assertNoRedirect();

    expect($issue->fresh()->isPublished())->toBeFalse();
});

test('the preview renders email-safe html', function () {
    $issue = Issue::factory()->create(['title' => 'Preview me']);

    $this->actingAs($issue->newsletter->user)
        ->get(route('issues.preview', $issue))
        ->assertOk()
        ->assertSee('Preview me')
        ->assertSee('role="presentation"', false);
});

test('readers can be exported as a csv with consent details', function () {
    $newsletter = Newsletter::factory()->create();
    Subscriber::factory()->for($newsletter)->create(['email' => 'reader@example.com', 'name' => '=HYPERLINK("x")']);

    $response = $this->actingAs($newsletter->user)->get(route('subscribers.export'));

    $response->assertOk();
    expect($response->streamedContent())->toContain('reader@example.com')->toContain("'=HYPERLINK");
});

test('the styleguide shows every component', function () {
    $this->get(appUrl('styleguide'))->assertOk()->assertSee('Component kit')->assertSee('Upgrade now');
});

test('admins can reach admin pages and buyers cannot', function () {
    $this->actingAs(buyer())->get(route('admin.users'))->assertForbidden();

    $admin = User::factory()->admin()->owning()->create();
    $this->actingAs($admin)->get(route('admin.users'))->assertOk();
    $this->actingAs($admin)->get(route('admin.reports'))->assertOk();
    $this->actingAs($admin)->get(route('admin.spend'))->assertOk();
});
