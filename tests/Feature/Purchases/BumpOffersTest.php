<?php

use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Livewire\Admin\Bumps;
use App\Livewire\Admin\Users;
use App\Livewire\Downloads\Index as Downloads;
use App\Models\BumpOffer;
use App\Models\BumpOfferFile;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\IpnNeedsAttention;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    configureLaunchpad();
    Notification::fake();
    Storage::fake(BumpOfferFile::DISK);
});

/**
 * A bump with one stored PDF and one stored image.
 */
function bumpWithFiles(Product $product = Product::FrontEnd): BumpOffer
{
    $bump = BumpOffer::factory()->forProduct($product)->create(['name' => 'Swipe Pack', 'price' => 27]);

    foreach ([['Guide.pdf', 'application/pdf'], ['Banner.png', 'image/png']] as [$name, $mime]) {
        $path = 'bumps/'.$bump->id.'/'.$name;
        Storage::disk(BumpOfferFile::DISK)->put($path, 'contents of '.$name);
        $bump->files()->create(['name' => $name, 'path' => $path, 'mime_type' => $mime, 'size' => 100]);
    }

    return $bump;
}

test('an upsell with a bump delivers that product\'s bump, not the front end\'s', function () {
    BumpOffer::factory()->create(['name' => 'Front end bump']);
    $oto3Bump = bumpWithFiles(Product::Oto3Growth);

    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto3Growth), 'transaction_id' => 'pi_oto3', 'bump' => 'true', 'total_amount' => '124.00']))->assertOk();

    $user = User::sole();
    expect($user->owns(Product::Oto3Growth))->toBeTrue()
        ->and($oto3Bump->isOwnedBy($user))->toBeTrue()
        ->and(Purchase::whereNotNull('bump_offer_id')->sole()->bump_offer_id)->toBe($oto3Bump->id)
        ->and(Purchase::where('product_key', Product::Oto3Growth)->sole()->amount)->toBe('97.00')
        ->and($user->owns(Product::BumpVault))->toBeFalse();
});

test('a bump taken on a product with no live bump still records the sale and alerts the admins', function () {
    $admin = User::factory()->admin()->create();
    BumpOffer::factory()->forProduct(Product::Oto1Delivery)->inactive()->create();

    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto1Delivery), 'transaction_id' => 'pi_pro', 'bump' => 'true']))->assertOk();

    expect(User::where('email', 'buyer@example.com')->sole()->owns(Product::Oto1Delivery))->toBeTrue()
        ->and(Purchase::whereNotNull('bump_offer_id')->exists())->toBeFalse();
    Notification::assertSentTo($admin, IpnNeedsAttention::class);
});

test('buyers see and download their bump files, and nobody else can', function () {
    $bump = bumpWithFiles();
    postIpn(ipnPayload(['bump' => 'true', 'total_amount' => '44.00']));
    $buyer = User::sole();
    $pdf = $bump->files->firstWhere('name', 'Guide.pdf');
    $image = $bump->files->firstWhere('name', 'Banner.png');

    $this->actingAs($buyer)->get(route('dashboard'))->assertSee(route('downloads.index'));
    Livewire::actingAs($buyer)->test(Downloads::class)->assertSee('Swipe Pack')->assertSee('Guide.pdf')->assertSee('Download all');

    $this->actingAs($buyer)->get(route('downloads.file', $pdf))->assertOk()->assertDownload('Guide.pdf');
    $this->actingAs($buyer)->get(route('downloads.file', ['file' => $image, 'inline' => 1]))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->actingAs($buyer)->get(route('downloads.zip', $bump))->assertOk()->assertDownload('swipe-pack.zip');

    $this->flushSession();
    $stranger = buyer();
    $this->actingAs($stranger)->get(route('downloads.file', $pdf))->assertForbidden();
    $this->actingAs($stranger)->get(route('downloads.zip', $bump))->assertForbidden();
    Livewire::actingAs($stranger)->test(Downloads::class)->assertDontSee('Swipe Pack');
});

test('a refunded buyer loses the bump files', function () {
    $bump = bumpWithFiles();
    postIpn(ipnPayload(['bump' => 'true']));
    postIpn(ipnPayload(['action' => 'REFUND']));

    $this->actingAs(User::sole())->get(route('downloads.file', $bump->files->first()))->assertForbidden();
});

test('replacing a product\'s bump keeps earlier buyers\' downloads and gives new buyers the new one', function () {
    $old = bumpWithFiles();
    postIpn(ipnPayload(['bump' => 'true']));
    $earlyBuyer = User::sole();

    $new = BumpOffer::factory()->inactive()->create(['name' => 'Graphics Pack']);
    $new->activate();

    postIpn(ipnPayload(['bump' => 'true', 'transaction_id' => 'pi_later', 'user' => ['email' => 'later@example.com']]));
    $laterBuyer = User::where('email', 'later@example.com')->sole();

    expect($old->fresh()->is_active)->toBeFalse()
        ->and($old->isOwnedBy($earlyBuyer))->toBeTrue()
        ->and($new->isOwnedBy($earlyBuyer))->toBeFalse()
        ->and($new->isOwnedBy($laterBuyer))->toBeTrue();
});

test('an admin sets up a bump and uploads its files', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(Bumps::class)
        ->call('edit')
        ->set('form.product_key', 'oto9_multi')
        ->set('form.name', '100 Social Templates')
        ->set('form.price', '19.95')
        ->call('save')
        ->assertHasNoErrors();

    $bump = BumpOffer::sole();
    expect($bump->product_key)->toBe(Product::Oto9Multi)->and($bump->is_active)->toBeTrue()->and($bump->price)->toBe('19.95');

    $this->post(route('admin.bumps.files.store', $bump), ['files' => [
        UploadedFile::fake()->create('Ebook.pdf', 500, 'application/pdf'),
        UploadedFile::fake()->image('Template-1.png'),
    ]])->assertRedirect(route('admin.bumps', ['editing' => $bump->id]));

    expect($bump->files()->pluck('name')->all())->toBe(['Ebook.pdf', 'Template-1.png']);
    Storage::disk(BumpOfferFile::DISK)->assertExists($bump->files()->first()->path);

    $this->post(route('admin.bumps.files.store', $bump), ['files' => [UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')]])
        ->assertSessionHasErrors('files.0');
});

test('only one bump per product is live, and a bought bump is switched off instead of deleted', function () {
    $this->actingAs(User::factory()->admin()->create());
    $first = bumpWithFiles();
    $second = BumpOffer::factory()->inactive()->create();

    Livewire::test(Bumps::class)->call('activate', $second->id);
    expect($first->fresh()->is_active)->toBeFalse()->and($second->fresh()->is_active)->toBeTrue();

    postIpn(ipnPayload(['bump' => 'true']));

    Livewire::test(Bumps::class)->call('delete', $second->id)->assertDispatched('toast', tone: 'warning');
    expect($second->fresh())->not->toBeNull()->and($second->fresh()->is_active)->toBeFalse();

    $path = $first->files->first()->path;
    Livewire::test(Bumps::class)->call('delete', $first->id);
    expect(BumpOffer::find($first->id))->toBeNull();
    Storage::disk(BumpOfferFile::DISK)->assertMissing($path);
});

test('buyers cannot manage bumps or upload files', function () {
    $bump = BumpOffer::factory()->create();
    $this->actingAs(buyer());

    Livewire::test(Bumps::class)->call('activate', $bump->id)->assertForbidden();
    $this->post(route('admin.bumps.files.store', $bump), ['files' => [UploadedFile::fake()->create('a.pdf', 1, 'application/pdf')]])->assertForbidden();
});

test('an admin can give a buyer a bump by hand and take it back', function () {
    $this->actingAs(User::factory()->admin()->create());
    $bump = BumpOffer::factory()->create(['unlocks_product' => Product::BumpVault]);
    $buyer = buyer();

    Livewire::test(Users::class)->call('select', $buyer->id)->set('grantProduct', 'bump:'.$bump->id)->call('grant');

    expect($bump->isOwnedBy($buyer))->toBeTrue()->and($buyer->fresh()->owns(Product::BumpVault))->toBeTrue();

    $line = Purchase::where('bump_offer_id', $bump->id)->sole();
    Livewire::test(Users::class)->call('select', $buyer->id)->call('revoke', $line->id);

    expect($bump->isOwnedBy($buyer))->toBeFalse()
        ->and(Purchase::where('action', PurchaseAction::Refund)->where('bump_offer_id', $bump->id)->exists())->toBeTrue();
});
