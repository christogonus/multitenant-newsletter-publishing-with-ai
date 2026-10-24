<?php

use App\Actions\RecordPurchase;
use App\Enums\IssueStatus;
use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Models\User;
use App\Notifications\WelcomeBuyer;
use Laravel\Dusk\Browser;

/*
 * Phase 1 exit gate: a buyer goes from purchase to a live public issue in under 15 minutes.
 * The app under test uses App\Ai\FakeNewsletterWriter (NEWSPUB_AI_FAKE=true in .env.dusk.local).
 */
test('a new buyer reaches a live public issue in under 15 minutes', function () {
    $startedAt = now();

    app(RecordPurchase::class)->handle('dusk-buyer@example.com', 'Dusk Buyer', Product::FrontEnd, PurchaseAction::Sale, 'DUSK-TXN-1');
    $welcomeUrl = WelcomeBuyer::setPasswordUrl(User::where('email', 'dusk-buyer@example.com')->sole());

    $this->browse(function (Browser $browser) use ($welcomeUrl) {
        $browser->visit($welcomeUrl)
            ->assertSee('Welcome to '.config('app.name'))
            ->type('password', 'a-strong-password-1')
            ->type('password_confirmation', 'a-strong-password-1')
            ->check('terms')
            ->press('Start my newsletter')
            ->waitForText('Start My Newsletter')
            ->clickLink('Start My Newsletter')
            ->waitForText('What will your newsletter be about?')
            ->press('Personal finance')
            ->waitForText('Name your newsletter')
            ->assertInputValue('#subdomain', 'money-made-simple')
            ->press('Create and write my first issue')
            ->waitForText('Email details', 20)
            ->press('Publish')
            ->waitForText('Your issue is live!', 15)
            ->visit('/p/money-made-simple')
            ->assertSee('Money Made Simple')
            ->assertSee('Three simple wins in Personal finance');
    });

    expect(Issue::where('status', IssueStatus::Published)->count())->toBe(1)
        ->and($startedAt->diffInMinutes(now()))->toBeLessThan(15);
});

test('a reader subscribes from the public page', function () {
    $user = User::factory()->owning()->create();
    $newsletter = Newsletter::factory()->for($user)->create(['subdomain' => 'dusk-weekly', 'name' => 'Dusk Weekly']);

    $this->browse(function (Browser $browser) {
        $browser->visit('/p/dusk-weekly')
            ->type('email', 'reader@example.com')
            ->pause(2100)
            ->press('Subscribe for free')
            ->waitForText('You are on the list');
    });

    expect($newsletter->subscribers()->where('email', 'reader@example.com')->exists())->toBeTrue();
});
