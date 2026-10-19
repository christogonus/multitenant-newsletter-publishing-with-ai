<?php

use App\Enums\IssueStatus;
use App\Enums\Product;
use App\Jobs\PushIssueJob;
use App\Livewire\Admin\Users;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Models\PlatformConnection;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function admin(): User
{
    return User::factory()->admin()->create();
}

function suspendedOwner(): User
{
    return User::factory()->owning(Product::FrontEnd, Product::Oto7Autopilot)->suspended()->create();
}

test('admins suspend a buyer with a private note and lift it again', function () {
    $owner = buyer(Product::FrontEnd);
    $this->actingAs(admin());

    $component = Livewire::test(Users::class)
        ->call('select', $owner->id)
        ->set('suspensionReason', 'Chargeback under review')
        ->call('suspend');

    expect($owner->fresh())->isSuspended()->toBeTrue()->suspension_reason->toBe('Chargeback under review');

    $component->call('unsuspend');

    expect($owner->fresh())->isSuspended()->toBeFalse()->suspension_reason->toBeNull();
});

test('admins cannot suspend or delete an admin or themselves', function (string $action) {
    $admin = admin();
    $otherAdmin = admin();
    $this->actingAs($admin);

    foreach ([$admin, $otherAdmin] as $target) {
        Livewire::test(Users::class)
            ->call('select', $target->id)
            ->set('deleteConfirmation', $target->email)
            ->call($action)
            ->assertForbidden();

        expect($target->fresh())->not->toBeNull()->isSuspended()->toBeFalse();
    }
})->with(['suspend', 'deleteUser']);

test('non-admins cannot suspend anyone', function () {
    $owner = buyer(Product::FrontEnd);
    $this->actingAs(buyer(Product::FrontEnd));

    Livewire::test(Users::class)->set('selectedId', $owner->id)->call('suspend')->assertForbidden();

    expect($owner->fresh()->isSuspended())->toBeFalse();
});

test('a suspended owner sees the on-hold page instead of the dashboard', function () {
    $owner = suspendedOwner();
    Newsletter::factory()->for($owner)->create();

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertForbidden()
        ->assertSee('Your account is on hold')
        ->assertSee(config('newspub.support_email'));
});

test('every page of a suspended owner\'s newsletter shows a branded coming-soon page', function (string $path) {
    $newsletter = Newsletter::factory()->for(suspendedOwner())->create(['name' => 'Acme Money Notes']);
    $issue = Issue::factory()->for($newsletter)->published()->create(['title' => 'Hidden issue']);

    $this->get(publicationUrl($newsletter, str_replace('{slug}', $issue->public_slug, $path)))
        ->assertStatus(503)
        ->assertHeader('Retry-After', '86400')
        ->assertSee('Coming soon')
        ->assertSee('Acme Money Notes')
        ->assertDontSee('Hidden issue');
})->with(['', 'issues/{slug}', 'feed']);

test('readers cannot sign up to a suspended owner\'s newsletter', function () {
    $newsletter = Newsletter::factory()->for(suspendedOwner())->create();

    $this->post(publicationUrl($newsletter, 'subscribe'), ['email' => 'reader@example.com'])->assertStatus(503);

    expect(Subscriber::query()->exists())->toBeFalse();
});

test('scheduled issues of a suspended owner wait until the suspension is lifted', function () {
    $owner = suspendedOwner();
    $issue = Issue::factory()->for(Newsletter::factory()->for($owner))->create(['status' => IssueStatus::Scheduled, 'scheduled_for' => now()->subMinute()]);

    $this->artisan('newspub:publish-scheduled')->assertSuccessful();
    expect($issue->fresh()->status)->toBe(IssueStatus::Scheduled);

    $owner->forceFill(['suspended_at' => null])->save();
    $this->artisan('newspub:publish-scheduled')->assertSuccessful();
    expect($issue->fresh()->status)->toBe(IssueStatus::Published);
});

test('a push queued before the suspension is not sent', function () {
    Http::preventStrayRequests();
    $newsletter = Newsletter::factory()->for(suspendedOwner())->create();
    $issue = Issue::factory()->for($newsletter)->published()->create();
    $connection = PlatformConnection::factory()->for($newsletter)->create();

    PushIssueJob::dispatchSync($issue, $connection);

    Http::assertNothingSent();
    expect($issue->deliveries()->exists())->toBeFalse();
});

test('admins delete a buyer with all newsletters and readers after typing the email', function () {
    $owner = buyer(Product::FrontEnd);
    $newsletters = Newsletter::factory()->for($owner)->count(2)->create();
    Subscriber::factory()->for($newsletters[0])->count(3)->create();
    Issue::factory()->for($newsletters[1])->create();
    $keptSubscriber = Subscriber::factory()->create();
    $this->actingAs(admin());

    $component = Livewire::test(Users::class)
        ->call('select', $owner->id)
        ->set('deleteConfirmation', 'wrong@example.com')
        ->call('deleteUser')
        ->assertHasErrors('deleteConfirmation');

    expect($owner->fresh())->not->toBeNull();

    $component->set('deleteConfirmation', strtoupper($owner->email))->call('deleteUser')->assertHasNoErrors();

    expect(User::query()->whereKey($owner->id)->exists())->toBeFalse()
        ->and(Newsletter::query()->whereIn('id', $newsletters->modelKeys())->exists())->toBeFalse()
        ->and(Subscriber::query()->pluck('id')->all())->toBe([$keptSubscriber->id])
        ->and(Issue::query()->exists())->toBeFalse();
});
