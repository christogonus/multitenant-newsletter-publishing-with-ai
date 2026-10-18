<?php

use App\Enums\Product;
use App\Enums\PurchaseSource;
use App\Livewire\Admin\Users;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('an admin creates a demo account that owns the chosen products and can sign in', function () {
    Notification::fake();
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(Users::class)
        ->call('openDemoForm')
        ->set('demoName', 'Demo Reviewer')
        ->set('demoEmail', 'Reviewer@Example.com')
        ->set('demoProducts', ['oto2_unlimited', 'oto8_premium'])
        ->call('createDemo')
        ->assertHasNoErrors();

    $demo = User::where('email', 'reviewer@example.com')->sole();
    $login = $component->get('demoLogin');

    expect($demo->is_demo)->toBeTrue()
        ->and($demo->is_admin)->toBeFalse()
        ->and($demo->email_verified_at)->not->toBeNull()
        ->and($demo->owns(Product::FrontEnd))->toBeTrue()
        ->and($demo->owns(Product::Oto2Unlimited))->toBeTrue()
        ->and($demo->owns(Product::Oto8Premium))->toBeTrue()
        ->and($demo->owns(Product::Oto12Reseller))->toBeFalse()
        ->and($demo->purchases->every(fn ($purchase) => $purchase->source === PurchaseSource::AdminGrant && (float) $purchase->amount === 0.0))->toBeTrue()
        ->and($login['email'])->toBe('reviewer@example.com')
        ->and(Hash::check($login['password'], $demo->password))->toBeTrue();

    Notification::assertNothingSent();
});

test('a demo account needs a new email and at least one product', function () {
    $this->actingAs(User::factory()->admin()->create());
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(Users::class)
        ->set('demoName', 'Demo')
        ->set('demoEmail', 'taken@example.com')
        ->set('demoProducts', [])
        ->call('createDemo')
        ->assertHasErrors(['demoEmail' => 'unique', 'demoProducts' => 'required']);

    expect(User::where('is_demo', true)->count())->toBe(0);
});

test('only demo accounts can be deleted from the admin area, with their publications', function () {
    $this->actingAs(User::factory()->admin()->create());
    $demo = User::factory()->create();
    $demo->forceFill(['is_demo' => true])->save();
    $newsletter = Newsletter::factory()->for($demo)->create();
    $buyer = buyer();

    Livewire::test(Users::class)->call('deleteDemo', $demo->id)->assertDispatched('toast', tone: 'success');

    expect(User::find($demo->id))->toBeNull()->and(Newsletter::find($newsletter->id))->toBeNull();

    Livewire::test(Users::class)->call('deleteDemo', $buyer->id)->assertNotFound();
    expect(User::find($buyer->id))->not->toBeNull();
});

test('the demo filter lists only demo accounts', function () {
    $this->actingAs(User::factory()->admin()->create());
    User::factory()->create(['name' => 'Real Buyer']);
    User::factory()->create(['name' => 'Showcase Demo'])->forceFill(['is_demo' => true])->save();

    Livewire::test(Users::class)->set('demoOnly', true)->assertSee('Showcase Demo')->assertDontSee('Real Buyer');
});

test('buyers cannot create demo accounts', function () {
    $this->actingAs(buyer());

    Livewire::test(Users::class)
        ->set('demoName', 'Sneaky')
        ->set('demoEmail', 'sneaky@example.com')
        ->call('createDemo')
        ->assertForbidden();

    expect(User::where('email', 'sneaky@example.com')->exists())->toBeFalse();
});

test('make-admin promotes an existing account, and --revoke removes it', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('newspub:make-admin', ['email' => 'OWNER@example.com'])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeTrue();

    $this->artisan('newspub:make-admin', ['email' => 'owner@example.com', '--revoke' => true])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeFalse();
});

test('make-admin creates a missing account with a one-time password', function () {
    $this->artisan('newspub:make-admin', ['email' => 'first@example.com', '--name' => 'First Admin'])
        ->expectsOutputToContain('Created admin first@example.com')
        ->assertSuccessful();

    $admin = User::where('email', 'first@example.com')->sole();
    expect($admin->is_admin)->toBeTrue()->and($admin->name)->toBe('First Admin');
});

test('make-admin refuses an invalid email', function () {
    $this->artisan('newspub:make-admin', ['email' => 'nope'])->assertFailed();

    expect(User::count())->toBe(0);
});
