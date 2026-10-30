<?php

use App\Actions\RecordPurchase;
use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Models\CalendarItem;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * The component state a browser would hold after loading the page.
 */
function loadedSnapshot(string $path): string
{
    preg_match('/wire:snapshot="([^"]+)"/', test()->get(appUrl($path))->assertOk()->getContent(), $matches);

    return htmlspecialchars_decode($matches[1], ENT_QUOTES);
}

/**
 * Posts a real Livewire update, so the persistent middleware runs (Livewire::test skips it).
 *
 * @param  list<array{path: string, method: string, params: list<mixed>}>  $calls
 * @param  array<string, mixed>  $updates
 */
function postLivewireUpdate(string $snapshot, array $updates = [], array $calls = [], ?string $host = null): TestResponse
{
    $uri = 'https://'.($host ?? config('newspub.app_host')).app('livewire')->getUpdateUri();

    return test()->withHeaders(['X-Livewire' => '1'])->postJson($uri, [
        'components' => [['snapshot' => $snapshot, 'updates' => $updates, 'calls' => $calls]],
    ]);
}

/**
 * Asks the Autopilot page to add a calendar item.
 */
function addCalendarItem(string $snapshot, ?string $host = null): TestResponse
{
    return postLivewireUpdate($snapshot, ['newTopic' => 'A topic'], [['path' => '', 'method' => 'addItem', 'params' => []]], $host);
}

beforeEach(function () {
    $this->newsletter = Newsletter::factory()->for(buyer(Product::Oto7Autopilot))->create();
    $this->owner = $this->newsletter->user;
    $this->actingAs($this->owner);
    $this->snapshot = loadedSnapshot('autopilot');
});

test('an owner of the product can use its actions', function () {
    addCalendarItem($this->snapshot)->assertOk();

    expect(CalendarItem::count())->toBe(1);
});

test('a refund takes effect on a page that is already open', function () {
    $sale = $this->owner->purchases()->where('product_key', Product::Oto7Autopilot)->sole();
    app(RecordPurchase::class)->handle($this->owner->email, $this->owner->name, Product::Oto7Autopilot, PurchaseAction::Refund, $sale->transaction_id, user: $this->owner);

    addCalendarItem($this->snapshot)->assertForbidden();

    expect(CalendarItem::count())->toBe(0);
});

test('another customer cannot reuse a buyer\'s page to get a feature they never bought', function () {
    $this->actingAs(Newsletter::factory()->create()->user);

    addCalendarItem($this->snapshot)->assertForbidden();

    expect(CalendarItem::count())->toBe(0);
});

test('a suspended owner cannot act from a page that is already open', function () {
    $this->owner->forceFill(['suspended_at' => now()])->save();

    addCalendarItem($this->snapshot)->assertForbidden();

    expect(CalendarItem::count())->toBe(0);
});

test('livewire actions are refused on a publication address, where the gates would not run', function () {
    addCalendarItem($this->snapshot, 'someone.'.config('newspub.public_root'))->assertNotFound();

    expect(CalendarItem::count())->toBe(0);
});

test('a removed admin can no longer load the admin pages they had open, on any address', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $this->actingAs($admin);
    $snapshot = loadedSnapshot('admin/users');

    $admin->forceFill(['is_admin' => false])->save();
    $this->actingAs($admin->fresh());

    postLivewireUpdate($snapshot)->assertForbidden();
    postLivewireUpdate($snapshot, host: 'someone.'.config('newspub.public_root'))->assertNotFound();
});
