<?php

use App\Enums\Product;
use App\Livewire\Account\ChooseOwnPassword;
use App\Models\User;
use App\Notifications\PasswordChangeCode;
use App\Notifications\UpgradeUnlocked;
use App\Notifications\WelcomeBuyer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    configureLaunchpad();
    config(['newspub.default_password' => 'Start-Here-2026']);
    Notification::fake();
});

function welcomeEmailHtml(User $user): string
{
    return (string) Notification::sent($user, WelcomeBuyer::class)->last()->toMail($user)->render();
}

test('a new buyer signs in with the default password, and the welcome email gives them everything they need', function () {
    postIpn(ipnPayload())->assertOk();
    $buyer = User::sole();

    $html = welcomeEmailHtml($buyer);

    expect($buyer->uses_default_password)->toBeTrue()
        ->and($html)->toContain('Start-Here-2026')->toContain($buyer->email)->toContain(route('login'))->toContain(route('support'))
        ->toContain('Get started in 10 minutes')->toContain(Product::FrontEnd->label())
        ->not->toContain('/welcome/');

    $this->post(route('login'), ['email' => $buyer->email, 'password' => 'Start-Here-2026'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($buyer);
    expect($buyer->fresh()->terms_accepted_at)->not->toBeNull();
});

test('buyers are reminded to change the starting password until they do', function () {
    postIpn(ipnPayload());
    $buyer = User::sole();
    $this->actingAs($buyer);

    $this->get(route('support'))->assertSee('You are still using the starting password');

    $component = Livewire::test(ChooseOwnPassword::class)->call('sendCode')->assertSet('codeSent', true);
    $code = Notification::sent($buyer, PasswordChangeCode::class)->sole()->code;

    $component->set('code', $code)->set('password', 'my-own-password-77')->set('password_confirmation', 'my-own-password-77')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('account'));

    $buyer->refresh();
    expect($buyer->uses_default_password)->toBeFalse()
        ->and($buyer->email_verified_at)->not->toBeNull()
        ->and(Hash::check('my-own-password-77', $buyer->password))->toBeTrue();
    $this->get(route('support'))->assertOk()->assertDontSee('You are still using the starting password');
});

test('the starting password cannot be replaced without the emailed code', function () {
    postIpn(ipnPayload());
    $buyer = User::sole();
    $this->actingAs($buyer);

    $this->put(route('user-password.update'), [
        'current_password' => 'Start-Here-2026',
        'password' => 'attacker-password-1',
        'password_confirmation' => 'attacker-password-1',
    ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

    Livewire::test(ChooseOwnPassword::class)
        ->call('sendCode')
        ->set('code', '000000')->set('password', 'attacker-password-1')->set('password_confirmation', 'attacker-password-1')
        ->call('save')
        ->assertHasErrors('code');

    expect(Hash::check('Start-Here-2026', $buyer->fresh()->password))->toBeTrue();
});

test('a code stops working after five wrong guesses', function () {
    postIpn(ipnPayload());
    $buyer = User::sole();
    $this->actingAs($buyer);

    $component = Livewire::test(ChooseOwnPassword::class)->call('sendCode')
        ->set('password', 'my-own-password-77')->set('password_confirmation', 'my-own-password-77');
    $code = Notification::sent($buyer, PasswordChangeCode::class)->sole()->code;
    $wrong = $code === '111111' ? '222222' : '111111';

    foreach (range(1, ChooseOwnPassword::MAX_CODE_ATTEMPTS) as $attempt) {
        $component->set('code', $wrong)->call('save');
    }

    $component->set('code', $code)->call('save')->assertHasErrors('code');
    expect($buyer->fresh()->uses_default_password)->toBeTrue();
});

test('nothing that could lock the owner out works until they choose their own password', function () {
    postIpn(ipnPayload());
    $buyer = User::sole();
    $this->actingAs($buyer);

    $this->get(route('account'))->assertOk()->assertSee('Email me a code')->assertDontSee('Set up two-step sign in')->assertDontSee('Delete my account</span>', false);

    $this->post(route('two-factor.enable'))->assertRedirect(route('account'));
    $this->delete(route('account.destroy'), ['password' => 'Start-Here-2026', 'confirm' => 'DELETE'])->assertRedirect(route('account'));
    $this->put(route('user-profile-information.update'), ['name' => 'New Name', 'email' => 'attacker@example.com'])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'email');

    $buyer->refresh();
    expect($buyer->exists)->toBeTrue()
        ->and($buyer->email)->toBe('buyer@example.com')
        ->and($buyer->two_factor_secret)->toBeNull();

    $this->put(route('user-profile-information.update'), ['name' => 'New Name', 'email' => 'buyer@example.com'])->assertSessionHasNoErrors();
    expect($buyer->fresh()->name)->toBe('New Name');
});

test('an account whose owner chose their own password is never switched to the default', function () {
    $owner = User::factory()->create(['email' => 'buyer@example.com', 'password' => 'their-own-secret', 'terms_accepted_at' => now()]);
    $owner->forceFill(['email_verified_at' => null])->save();

    postIpn(ipnPayload());

    $owner->refresh();
    expect(Hash::check('their-own-secret', $owner->password))->toBeTrue()
        ->and($owner->uses_default_password)->toBeFalse()
        ->and(welcomeEmailHtml($owner))->toContain('Choose my password')->not->toContain('Start-Here-2026');
});

test('a buyer still on the default password gets upgrade emails without ever signing in', function () {
    postIpn(ipnPayload());
    $buyer = User::sole();

    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto4Sponsor), 'transaction_id' => 'pi_4004']));

    Notification::assertSentToTimes($buyer, WelcomeBuyer::class, 1);
    Notification::assertSentToTimes($buyer, UpgradeUnlocked::class, 1);
});

test('anyone can open the support page to find the login details', function () {
    $this->get(route('support'))->assertOk()->assertSee('Your login details')->assertSee('Start-Here-2026')->assertSee(route('login'));
});

test('without a default password the support page points to the welcome email and password reset', function () {
    config(['newspub.default_password' => null]);

    $this->get(route('support'))->assertOk()->assertSee('Use the password you chose')->assertDontSee('Start-Here-2026');
});

test('existing buyers who never set a password are moved to the default password', function () {
    postIpn(ipnPayload());
    $waiting = User::sole();
    $waiting->forceFill(['password' => 'random-unknown', 'uses_default_password' => false])->save();

    postIpn(ipnPayload(['user' => ['email' => 'set-up@example.com'], 'transaction_id' => 'pi_2']));
    $setUp = User::query()->where('email', 'set-up@example.com')->sole();
    $setUp->forceFill(['password' => 'chosen-password', 'uses_default_password' => false, 'email_verified_at' => now()])->save();

    postIpn(ipnPayload(['user' => ['email' => 'reseller@example.com'], 'transaction_id' => 'pi_3']));
    $reseller = User::query()->where('email', 'reseller@example.com')->sole();
    $reseller->forceFill(['password' => 'reseller-password', 'uses_default_password' => false, 'terms_accepted_at' => now()])->save();

    $this->artisan('newspub:apply-default-password', ['--dry-run' => true])->expectsOutputToContain('1 account would')->assertSuccessful();
    expect($waiting->fresh()->uses_default_password)->toBeFalse();

    $this->artisan('newspub:apply-default-password')->assertSuccessful();

    expect(Hash::check('Start-Here-2026', $waiting->fresh()->password))->toBeTrue()
        ->and($waiting->fresh()->uses_default_password)->toBeTrue()
        ->and(Hash::check('chosen-password', $setUp->fresh()->password))->toBeTrue()
        ->and(Hash::check('reseller-password', $reseller->fresh()->password))->toBeTrue();
});

test('the default password command refuses to run until a default password is set', function () {
    config(['newspub.default_password' => '']);

    $this->artisan('newspub:apply-default-password')->assertFailed();
});
