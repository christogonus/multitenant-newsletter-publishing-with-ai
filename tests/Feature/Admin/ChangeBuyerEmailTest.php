<?php

use App\Livewire\Account\ChangeEmail;
use App\Livewire\Account\ChooseOwnPassword;
use App\Livewire\Admin\Users;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\EmailChangeCode;
use App\Notifications\EmailChanged;
use App\Notifications\PasswordChangeCode;
use App\Notifications\WelcomeBuyer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\Finder\SplFileInfo;

beforeEach(function () {
    configureLaunchpad();
    config(['newspub.default_password' => 'Start-Here-2026']);
    Notification::fake();
});

function moveBuyerEmail(User $buyer, string $email = 'real@example.com'): Testable
{
    test()->actingAs(User::factory()->admin()->create());

    return Livewire::test(Users::class)->call('select', $buyer->id)->set('newEmail', $email)->call('changeEmail');
}

test('a buyer who mistyped their checkout email is moved to their real one and can then choose a password', function () {
    postIpn(ipnPayload(['user' => ['email' => 'name@gamil.com']]));
    $buyer = User::sole();

    moveBuyerEmail($buyer, 'Real@Example.com')->assertHasNoErrors()->assertDispatched('toast', tone: 'success');

    $buyer->refresh();
    expect($buyer->email)->toBe('real@example.com')
        ->and($buyer->uses_default_password)->toBeTrue()
        ->and(Hash::check('Start-Here-2026', $buyer->password))->toBeTrue()
        ->and(Purchase::pluck('buyer_email')->unique()->all())->toBe(['name@gamil.com']);

    $welcome = (string) Notification::sent($buyer, WelcomeBuyer::class)->last()->toMail($buyer)->render();
    expect($welcome)->toContain('real@example.com')->toContain('Start-Here-2026');

    $this->post(route('logout'));
    $this->post(route('login'), ['email' => 'real@example.com', 'password' => 'Start-Here-2026'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($buyer);

    Livewire::test(ChooseOwnPassword::class)->call('sendCode')->assertSet('codeSent', true);
    Notification::assertSentTo($buyer, PasswordChangeCode::class);
});

test('the old address is told, without learning the new one', function () {
    postIpn(ipnPayload(['user' => ['email' => 'name@gamil.com']]));

    moveBuyerEmail(User::sole());

    Notification::assertSentOnDemand(EmailChanged::class, function (EmailChanged $notification, array $channels, object $notifiable) {
        return $notifiable->routes['mail'] === 'name@gamil.com'
            && ! str_contains((string) $notification->toMail($notifiable)->render(), 'real@example.com');
    });
});

test('a later sale paid with the old email still reaches the moved account', function () {
    postIpn(ipnPayload(['user' => ['email' => 'name@gamil.com']]));
    moveBuyerEmail(User::sole());

    postIpn(ipnPayload(['user' => ['email' => 'name@gamil.com'], 'transaction_id' => 'pi_later', 'id' => 99999]))->assertOk();

    expect(User::where('is_admin', false)->sole()->email)->toBe('real@example.com');
});

test('moving an account with its own password replaces it and kills old set-password links', function () {
    $buyer = User::factory()->create(['email' => 'old@example.com', 'password' => 'known-to-a-thief']);
    $oldLink = WelcomeBuyer::setPasswordUrl($buyer);

    moveBuyerEmail($buyer)->assertHasNoErrors();

    $buyer->refresh();
    expect($buyer->email)->toBe('real@example.com')
        ->and(Hash::check('known-to-a-thief', $buyer->password))->toBeFalse();

    $this->post(route('logout'));
    $this->get($oldLink)->assertRedirect(route('login'));

    $welcome = (string) Notification::sent($buyer, WelcomeBuyer::class)->last()->toMail($buyer)->render();
    expect($welcome)->toContain('/welcome/')->not->toContain('Start-Here-2026');
    $this->get(WelcomeBuyer::setPasswordUrl($buyer))->assertOk();
});

test('codes still waiting to be typed stop working once the account is moved', function () {
    $buyer = User::factory()->create(['email' => 'old@example.com', 'password' => 'known-to-a-thief']);
    $this->actingAs($buyer);
    $pending = Livewire::test(ChangeEmail::class)->set('email', 'thief@example.com')->set('password', 'known-to-a-thief')->call('sendCode');

    $code = null;
    Notification::assertSentOnDemand(EmailChangeCode::class, function (EmailChangeCode $notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    moveBuyerEmail($buyer);

    $this->actingAs($buyer->fresh());
    $pending->set('code', $code)->call('confirm')->assertHasErrors('code');
    expect($buyer->fresh()->email)->toBe('real@example.com');
});

test('an email in use on another account, paid with by one, or already on this one is refused', function (string $email) {
    $buyer = User::factory()->create(['email' => 'old@example.com', 'password' => 'their-own-secret']);
    User::factory()->create(['email' => 'taken@example.com']);
    Purchase::factory()->for(User::factory())->create(['buyer_email' => 'paid@example.com']);

    moveBuyerEmail($buyer, $email)->assertHasErrors('newEmail');

    expect($buyer->fresh()->email)->toBe('old@example.com')
        ->and(Hash::check('their-own-secret', $buyer->fresh()->password))->toBeTrue();
    Notification::assertNothingSent();
})->with(['taken@example.com', 'TAKEN@example.com', 'paid@example.com', 'old@example.com', 'not-an-email']);

test('admins cannot move an admin account, their own, or a shared demo account', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $demo = User::factory()->create();
    $demo->forceFill(['is_demo' => true])->save();
    $this->actingAs($admin);

    foreach ([$admin, $otherAdmin, $demo] as $account) {
        Livewire::test(Users::class)->call('select', $account->id)->set('newEmail', 'real@example.com')->call('changeEmail')->assertForbidden();
    }

    expect(User::where('email', 'real@example.com')->exists())->toBeFalse();
});

test('no submit button carries a confirm box, because Livewire only honours it on the form', function () {
    $offenders = collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $view) => preg_match('/<x-ui\.button\b(?=[^\n]*type="submit")(?=[^\n]*wire:confirm)/', $view->getContents()))
        ->map(fn (SplFileInfo $view) => $view->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

test('buyers cannot move an account', function () {
    $target = User::factory()->create(['email' => 'old@example.com']);
    $this->actingAs(buyer());

    Livewire::test(Users::class)->set('selectedId', $target->id)->set('newEmail', 'thief@example.com')->call('changeEmail')->assertForbidden();

    expect($target->fresh()->email)->toBe('old@example.com');
});
