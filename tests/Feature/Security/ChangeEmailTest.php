<?php

use App\Livewire\Account\ChangeEmail;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\EmailChangeCode;
use App\Notifications\EmailChanged;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->owner = User::factory()->create(['email' => 'owner@example.com', 'password' => 'their-own-secret']);
    $this->actingAs($this->owner);
});

function requestEmailChange(string $email = 'new@example.com', string $password = 'their-own-secret'): Testable
{
    return Livewire::test(ChangeEmail::class)->set('email', $email)->set('password', $password)->call('sendCode');
}

function emailChangeCodeSentTo(string $email): string
{
    $code = null;

    Notification::assertSentOnDemand(EmailChangeCode::class, function (EmailChangeCode $notification, array $channels, object $notifiable) use ($email, &$code) {
        $code = $notification->code;

        return $notifiable->routes['mail'] === $email;
    });

    return $code;
}

test('the email only moves once the code from the new inbox is typed, and the old inbox is then told', function () {
    $component = requestEmailChange('New@Example.com')->assertHasNoErrors()->assertSet('pendingEmail', 'new@example.com');

    expect($this->owner->fresh()->email)->toBe('owner@example.com');
    Notification::assertSentOnDemandTimes(EmailChanged::class, 0);
    $this->get(route('account'))->assertSee('Change email');

    $component->set('code', emailChangeCodeSentTo('new@example.com'))->call('confirm')->assertHasNoErrors()->assertRedirect(route('account'));

    expect($this->owner->fresh()->email)->toBe('new@example.com');
    Notification::assertSentOnDemand(EmailChanged::class, fn (EmailChanged $notification, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'owner@example.com' && $notification->newEmail === 'new@example.com');

    $this->post(route('logout'));
    $this->post(route('login'), ['email' => 'new@example.com', 'password' => 'their-own-secret'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($this->owner);
});

test('the profile form cannot move the email around the confirmation', function () {
    $this->put(route('user-profile-information.update'), ['name' => 'New Name', 'email' => 'attacker@example.com'])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'email');

    expect($this->owner->fresh()->email)->toBe('owner@example.com');

    $this->put(route('user-profile-information.update'), ['name' => 'New Name', 'email' => 'owner@example.com'])->assertSessionHasNoErrors();
    expect($this->owner->fresh()->name)->toBe('New Name');
});

test('no code is sent without the current password', function () {
    requestEmailChange(password: 'wrong-password')->assertHasErrors('password')->assertSet('pendingEmail', null);

    Notification::assertNothingSent();
});

test('an email in use on another account, paid with by one, or already yours is refused', function (string $email) {
    User::factory()->create(['email' => 'taken@example.com']);
    Purchase::factory()->for(User::factory())->create(['buyer_email' => 'paid@example.com']);

    requestEmailChange($email)->assertHasErrors('email');

    Notification::assertNothingSent();
})->with(['taken@example.com', 'TAKEN@example.com', 'paid@example.com', 'owner@example.com']);

test('an address taken after the code was sent is refused at confirmation', function () {
    $component = requestEmailChange();
    User::factory()->create(['email' => 'new@example.com']);

    $component->set('code', emailChangeCodeSentTo('new@example.com'))->call('confirm')->assertHasErrors('code');

    expect($this->owner->fresh()->email)->toBe('owner@example.com');
});

test('a code stops working after five wrong guesses', function () {
    $component = requestEmailChange();
    $code = emailChangeCodeSentTo('new@example.com');
    $wrong = $code === '111111' ? '222222' : '111111';

    foreach (range(1, ChangeEmail::MAX_CODE_ATTEMPTS) as $attempt) {
        $component->set('code', $wrong)->call('confirm')->assertHasErrors('code');
    }

    $component->set('code', $code)->call('confirm')->assertHasErrors('code');

    expect($this->owner->fresh()->email)->toBe('owner@example.com');
});

test('a code only works once and only for the account that asked for it', function () {
    $component = requestEmailChange();
    $code = emailChangeCodeSentTo('new@example.com');

    $this->actingAs(User::factory()->create(['email' => 'other@example.com']));
    Livewire::test(ChangeEmail::class)->set('code', $code)->call('confirm')->assertHasErrors('code');
    expect(User::where('email', 'new@example.com')->exists())->toBeFalse();

    $this->actingAs($this->owner);
    $component->set('code', $code)->call('confirm')->assertHasNoErrors();
    $this->owner->fresh()->forceFill(['email' => 'owner@example.com'])->save();

    Livewire::test(ChangeEmail::class)->set('code', $code)->call('confirm')->assertHasErrors('code');
    expect($this->owner->fresh()->email)->toBe('owner@example.com');
});

test('starting over throws the code away', function () {
    $component = requestEmailChange();
    $code = emailChangeCodeSentTo('new@example.com');

    $component->call('startOver')->assertSet('pendingEmail', null)->set('code', $code)->call('confirm')->assertHasErrors('code');

    expect($this->owner->fresh()->email)->toBe('owner@example.com');
});

test('requests stop after a few tries, even with the wrong password', function () {
    foreach (range(1, ChangeEmail::MAX_REQUESTS) as $attempt) {
        requestEmailChange(password: 'wrong-password');
    }

    requestEmailChange()->assertHasErrors('email');

    Notification::assertNothingSent();
});

test('demo accounts and accounts on the starting password cannot change their email', function (array $attributes) {
    $this->owner->forceFill($attributes)->save();

    Livewire::test(ChangeEmail::class)->call('sendCode')->assertForbidden();
    Livewire::test(ChangeEmail::class)->set('code', '123456')->call('confirm')->assertForbidden();

    $this->get(route('account'))->assertOk()->assertDontSee('Change email');
    Notification::assertNothingSent();
})->with([
    'demo' => [['is_demo' => true]],
    'starting password' => [['uses_default_password' => true]],
]);
