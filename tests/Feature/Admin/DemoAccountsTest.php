<?php

use App\Enums\Product;
use App\Enums\PurchaseSource;
use App\Livewire\Admin\Users;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

test('an admin creates a demo account that owns the chosen products and can sign in', function () {
    Notification::fake();
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(Users::class)
        ->call('openDemoForm')
        ->set('demoName', 'Demo Reviewer')
        ->set('demoEmail', 'Reviewer@Example.com')
        ->set('demoProducts', ['oto2_gold', 'oto8_premium'])
        ->call('createDemo')
        ->assertHasNoErrors();

    $demo = User::where('email', 'reviewer@example.com')->sole();
    $login = $component->get('demoLogin');

    expect($demo->is_demo)->toBeTrue()
        ->and($demo->is_admin)->toBeFalse()
        ->and($demo->email_verified_at)->not->toBeNull()
        ->and($demo->owns(Product::FrontEnd))->toBeTrue()
        ->and($demo->owns(Product::Oto2Gold))->toBeTrue()
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

test('make-admin refuses an account still on the shared starting password', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);
    $user->forceFill(['uses_default_password' => true])->save();

    $this->artisan('newspub:make-admin', ['email' => 'owner@example.com'])->assertFailed();

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

function sharedDemo(): User
{
    $demo = User::factory()->create(['email' => 'demo@example.com', 'password' => 'shared-demo-pass']);
    $demo->forceFill(['is_demo' => true])->save();

    return $demo;
}

test('people signed in to a demo account cannot change its password, email or two-step sign in, or delete it', function () {
    $demo = sharedDemo();
    $this->actingAs($demo);

    $this->get(route('account'))->assertOk()->assertSee('Shared demo account')
        ->assertDontSee('Change password')->assertDontSee('Set up two-step sign in')->assertDontSee('Delete my account</span>', false);

    $this->put(route('user-password.update'), [
        'current_password' => 'shared-demo-pass',
        'password' => 'hijacked-password-1',
        'password_confirmation' => 'hijacked-password-1',
    ])->assertRedirect(route('account'))->assertSessionHas('status', 'demo-account-locked');
    $this->put(route('user-profile-information.update'), ['name' => 'Hijacker', 'email' => 'hijacker@example.com'])->assertRedirect(route('account'));
    $this->post(route('two-factor.enable'))->assertRedirect(route('account'));
    $this->delete(route('account.destroy'), ['password' => 'shared-demo-pass', 'confirm' => 'DELETE'])->assertRedirect(route('account'));

    $demo->refresh();
    expect(Hash::check('shared-demo-pass', $demo->password))->toBeTrue()
        ->and($demo->email)->toBe('demo@example.com')
        ->and($demo->two_factor_secret)->toBeNull();
});

test('a password reset link cannot change a demo account password', function () {
    $demo = sharedDemo();

    $this->post(route('password.update'), [
        'token' => Password::createToken($demo),
        'email' => $demo->email,
        'password' => 'hijacked-password-1',
        'password_confirmation' => 'hijacked-password-1',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('shared-demo-pass', $demo->fresh()->password))->toBeTrue();
});

test('an admin gives a demo account a new password and turns off two-step sign in, but never for a real buyer', function () {
    $demo = sharedDemo();
    $demo->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
    $buyer = User::factory()->create(['password' => 'buyers-own-pass']);
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(Users::class)->call('select', $demo->id)->call('resetDemoPassword');

    $demo->refresh();
    expect(Hash::check($component->get('demoNewPassword'), $demo->password))->toBeTrue()
        ->and($demo->two_factor_secret)->toBeNull()
        ->and($demo->two_factor_confirmed_at)->toBeNull();

    $this->post(route('logout'));
    $this->post(route('login'), ['email' => $demo->email, 'password' => $component->get('demoNewPassword')])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($demo);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Users::class)->call('select', $buyer->id)->call('resetDemoPassword')->assertNotFound();
    expect(Hash::check('buyers-own-pass', $buyer->fresh()->password))->toBeTrue();
});
