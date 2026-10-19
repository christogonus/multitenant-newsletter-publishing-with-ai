<?php

use App\Enums\PaidPlan;
use App\Enums\PaymentClaimStatus;
use App\Enums\PaymentProvider;
use App\Enums\Product;
use App\Livewire\Issues\Editor;
use App\Livewire\Sponsors\PaidSubscriptions;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Models\PaymentAccount;
use App\Models\PaymentClaim;
use App\Models\Subscriber;
use App\Notifications\ReaderPaymentClaimed;
use App\Notifications\ReaderSignInCode;
use App\Support\IssueRenderer;
use App\Support\PaidReaders;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => Http::preventStrayRequests());

function paidNewsletter(Product ...$products): Newsletter
{
    return Newsletter::factory()->for(buyer(Product::FrontEnd, Product::Oto4Sponsor, ...$products))->create([
        'paid_enabled' => true,
        'paid_monthly_cents' => 500,
        'paid_yearly_cents' => 5000,
    ]);
}

function lockedIssue(Newsletter $newsletter): Issue
{
    return Issue::factory()->for($newsletter)->published()->create([
        'title' => 'Inside the deal',
        'is_paid_only' => true,
        'blocks' => [
            ['id' => 'a', 'type' => 'heading', 'text' => 'Opening heading'],
            ['id' => 'b', 'type' => 'paragraph', 'text' => 'Free opening paragraph.'],
            ['id' => 'c', 'type' => 'paragraph', 'text' => 'Secret paying part.'],
        ],
    ]);
}

function payingReader(Newsletter $newsletter, string $email = 'reader@example.com', ?CarbonInterface $until = null): Subscriber
{
    return Subscriber::factory()->for($newsletter)->create([
        'email' => $email,
        'paid_until' => $until ?? now()->addMonth(),
        'paid_via' => PaymentProvider::Manual,
    ]);
}

/**
 * The sign-in code emailed (to the given address, when one is passed) during the test.
 */
function sentCode(?string $email = null): string
{
    $code = null;

    Notification::assertSentOnDemand(ReaderSignInCode::class, function (ReaderSignInCode $notification, array $channels, object $notifiable) use (&$code, $email) {
        $code = $notification->code;

        return $email === null || $notifiable->routes['mail'] === $email;
    });

    return $code;
}

test('a locked issue shows only its opening and a continue-reading prompt', function () {
    $newsletter = paidNewsletter();
    $issue = lockedIssue($newsletter);

    $this->get(publicationUrl($newsletter, 'issues/'.$issue->public_slug))
        ->assertOk()
        ->assertSee('Opening heading')
        ->assertSee('Free opening paragraph.')
        ->assertDontSee('Secret paying part.')
        ->assertSee('The rest of this issue is for paying readers')
        ->assertSee(publicationUrl($newsletter, 'members?next='.$issue->public_slug), false);
});

test('an issue marked for paying readers is free when paid subscriptions are off or the owner lacks OTO 4', function (Newsletter $newsletter) {
    $issue = lockedIssue($newsletter);

    $this->get(publicationUrl($newsletter, 'issues/'.$issue->public_slug))->assertOk()->assertSee('Secret paying part.');
})->with([
    'paid subscriptions off' => fn () => Newsletter::factory()->for(buyer(Product::FrontEnd, Product::Oto4Sponsor))->create(['paid_enabled' => false]),
    'owner without OTO 4' => fn () => Newsletter::factory()->for(buyer(Product::FrontEnd))->create(['paid_enabled' => true, 'paid_monthly_cents' => 500]),
]);

test('a signed-in paying reader reads the whole issue and others do not', function () {
    $newsletter = paidNewsletter();
    $issue = lockedIssue($newsletter);
    payingReader($newsletter, 'paid@example.com');
    payingReader($newsletter, 'lapsed@example.com', now()->subDay());
    Subscriber::factory()->for($newsletter)->create(['email' => 'free@example.com']);
    $url = publicationUrl($newsletter, 'issues/'.$issue->public_slug);

    $this->withSession(['paid_reader.'.$newsletter->id => 'paid@example.com'])->get($url)->assertSee('Secret paying part.');
    $this->withSession(['paid_reader.'.$newsletter->id => 'lapsed@example.com'])->get($url)->assertDontSee('Secret paying part.');
    $this->withSession(['paid_reader.'.$newsletter->id => 'free@example.com'])->get($url)->assertDontSee('Secret paying part.');
});

test('a paying reader signs in with an emailed code and goes back to the locked issue', function () {
    Notification::fake();
    $newsletter = paidNewsletter();
    $issue = lockedIssue($newsletter);
    payingReader($newsletter);

    $this->get(publicationUrl($newsletter, 'members?next='.$issue->public_slug))->assertOk()->assertSee('Email me a code');
    $this->post(publicationUrl($newsletter, 'members/code'), ['email' => 'Reader@Example.com'])
        ->assertRedirect(publicationUrl($newsletter, 'members'))
        ->assertSessionHas('paid_reader_pending.'.$newsletter->id, 'reader@example.com');

    $code = sentCode('reader@example.com');

    $this->post(publicationUrl($newsletter, 'members/verify'), ['code' => $code])
        ->assertRedirect(publicationUrl($newsletter, 'issues/'.$issue->public_slug))
        ->assertSessionHas('paid_reader.'.$newsletter->id, 'reader@example.com');
});

test('a wrong code does not sign the reader in and the code stops working after too many guesses', function () {
    $newsletter = paidNewsletter();
    $paidReaders = app(PaidReaders::class);
    Notification::fake();
    $paidReaders->sendCode($newsletter, 'reader@example.com');
    $code = sentCode();

    $this->withSession(['paid_reader_pending.'.$newsletter->id => 'reader@example.com'])
        ->post(publicationUrl($newsletter, 'members/verify'), ['code' => '000000'])
        ->assertSessionHasErrors('code')
        ->assertSessionMissing('paid_reader.'.$newsletter->id);

    foreach (range(2, PaidReaders::MAX_CODE_ATTEMPTS) as $attempt) {
        expect($paidReaders->checkCode($newsletter, 'reader@example.com', '000000'))->toBeFalse();
    }

    expect($paidReaders->checkCode($newsletter, 'reader@example.com', $code))->toBeFalse();
});

test('the paying-reader pages do not exist while paid subscriptions are off', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::FrontEnd, Product::Oto4Sponsor))->create();

    $this->get(publicationUrl($newsletter, 'members'))->assertNotFound();
});

test('a reader subscribes through Stripe and gets access until the end of the paid period', function () {
    $newsletter = paidNewsletter();
    $issue = lockedIssue($newsletter);
    PaymentAccount::factory()->for($newsletter)->create();
    $reference = app(PaidReaders::class)->reference($newsletter, 'reader@example.com');
    $periodEnd = now()->addMonth()->startOfSecond();

    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
        'api.stripe.com/v1/checkout/sessions/cs_test_1*' => Http::response([
            'client_reference_id' => $reference,
            'status' => 'complete',
            'metadata' => ['plan' => 'yearly'],
            'subscription' => ['id' => 'sub_1', 'status' => 'active', 'items' => ['data' => [['current_period_end' => $periodEnd->timestamp]]]],
        ]),
    ]);

    $session = ['paid_reader.'.$newsletter->id => 'reader@example.com', 'paid_reader_next.'.$newsletter->id => $issue->public_slug];

    $this->withSession($session)
        ->post(publicationUrl($newsletter, 'members/checkout'), ['provider' => 'stripe', 'plan' => 'yearly'])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
        && $request['customer_email'] === 'reader@example.com'
        && $request['client_reference_id'] === $reference
        && $request['line_items'][0]['price_data']['unit_amount'] === 5000
        && $request['line_items'][0]['price_data']['recurring']['interval'] === 'year');

    $this->withSession($session)
        ->get(publicationUrl($newsletter, 'members/complete/stripe?session_id=cs_test_1'))
        ->assertRedirect(publicationUrl($newsletter, 'issues/'.$issue->public_slug));

    $subscriber = Subscriber::query()->where('email', 'reader@example.com')->sole();
    expect($subscriber->paid_until->timestamp)->toBe($periodEnd->timestamp)
        ->and($subscriber->paid_via)->toBe(PaymentProvider::Stripe)
        ->and($subscriber->paid_plan)->toBe(PaidPlan::Yearly)
        ->and($subscriber->payment_reference)->toBe('sub_1');
});

test('a Stripe payment made for another reader does not grant access', function () {
    $newsletter = paidNewsletter();
    PaymentAccount::factory()->for($newsletter)->create();

    Http::fake(['api.stripe.com/v1/checkout/sessions/cs_test_1*' => Http::response([
        'client_reference_id' => app(PaidReaders::class)->reference($newsletter, 'someone-else@example.com'),
        'status' => 'complete',
        'subscription' => ['id' => 'sub_1', 'status' => 'active', 'current_period_end' => now()->addMonth()->timestamp],
    ])]);

    $this->withSession(['paid_reader.'.$newsletter->id => 'reader@example.com'])
        ->get(publicationUrl($newsletter, 'members/complete/stripe?session_id=cs_test_1'))
        ->assertSessionHasErrors('payment');

    expect(Subscriber::query()->whereNotNull('paid_until')->exists())->toBeFalse();
});

test('a reader subscribes through PayPal, which creates the plan once and remembers it', function () {
    $newsletter = paidNewsletter();
    $account = PaymentAccount::factory()->paypal()->for($newsletter)->create();
    $reference = app(PaidReaders::class)->reference($newsletter, 'reader@example.com');

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'api-m.sandbox.paypal.com/v1/catalogs/products' => Http::response(['id' => 'PROD-1'], 201),
        'api-m.sandbox.paypal.com/v1/billing/plans' => Http::response(['id' => 'P-MONTHLY'], 201),
        'api-m.sandbox.paypal.com/v1/billing/subscriptions' => Http::response(['id' => 'I-ABC', 'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/approve/I-ABC']]], 201),
        'api-m.sandbox.paypal.com/v1/billing/subscriptions/I-ABC' => Http::response([
            'status' => 'ACTIVE',
            'custom_id' => $reference,
            'plan_id' => 'P-MONTHLY',
            'billing_info' => ['next_billing_time' => '2027-01-19T10:00:00Z'],
        ]),
    ]);

    $session = ['paid_reader.'.$newsletter->id => 'reader@example.com'];

    foreach ([1, 2] as $checkout) {
        $this->withSession($session)
            ->post(publicationUrl($newsletter, 'members/checkout'), ['provider' => 'paypal', 'plan' => 'monthly'])
            ->assertRedirect('https://www.sandbox.paypal.com/approve/I-ABC');
    }

    Http::assertSentCount(6);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v1/billing/plans')
        && $request['billing_cycles'][0]['pricing_scheme']['fixed_price'] === ['value' => '5.00', 'currency_code' => 'USD']
        && $request['billing_cycles'][0]['frequency']['interval_unit'] === 'MONTH');
    expect($account->fresh()->detail('plans'))->toBe(['monthly:500:USD' => 'P-MONTHLY']);

    $this->withSession($session)->get(publicationUrl($newsletter, 'members/complete/paypal?subscription_id=I-ABC'));

    $subscriber = Subscriber::query()->where('email', 'reader@example.com')->sole();
    expect($subscriber->paid_until->toIso8601ZuluString())->toBe('2027-01-19T10:00:00Z')
        ->and($subscriber->paid_via)->toBe(PaymentProvider::PayPal)
        ->and($subscriber->paid_plan)->toBe(PaidPlan::Monthly);
});

test('a lapsed Stripe reader whose subscription renewed keeps reading', function () {
    $newsletter = paidNewsletter();
    $issue = lockedIssue($newsletter);
    PaymentAccount::factory()->for($newsletter)->create();
    $subscriber = payingReader($newsletter, until: now()->subHour());
    $subscriber->update(['paid_via' => PaymentProvider::Stripe, 'payment_reference' => 'sub_1']);
    $renewedUntil = now()->addMonth()->startOfSecond();

    Http::fake(['api.stripe.com/v1/subscriptions/sub_1' => Http::response(['status' => 'active', 'items' => ['data' => [['current_period_end' => $renewedUntil->timestamp]]]])]);

    $this->withSession(['paid_reader.'.$newsletter->id => 'reader@example.com'])
        ->get(publicationUrl($newsletter, 'issues/'.$issue->public_slug))
        ->assertSee('Secret paying part.');

    expect($subscriber->fresh()->paid_until->timestamp)->toBe($renewedUntil->timestamp);
});

test('emails, platform pushes and the feed carry only the opening of a locked issue', function () {
    $newsletter = paidNewsletter();
    $issue = lockedIssue($newsletter);

    expect(IssueRenderer::emailHtml($issue))->toContain('Free opening paragraph.')->not->toContain('Secret paying part.')
        ->and(IssueRenderer::contentHtml($issue))->toContain('Continue reading on the web')->not->toContain('Secret paying part.')
        ->and(IssueRenderer::emailHtml($issue, complete: true))->toContain('Secret paying part.');

    $this->get(publicationUrl($newsletter, 'feed'))->assertOk()->assertDontSee('Secret paying part.', false);
});

test('owners need a price before turning paid subscriptions on', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::FrontEnd, Product::Oto4Sponsor))->create();
    $this->actingAs($newsletter->user);

    Livewire::test(PaidSubscriptions::class)
        ->set('paidEnabled', true)
        ->call('saveSettings')
        ->assertHasErrors('monthlyPrice')
        ->set('yearlyPrice', '49.99')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($newsletter->fresh())->paid_enabled->toBeTrue()->paid_yearly_cents->toBe(4999)->paid_monthly_cents->toBeNull();
});

test('owners connect Stripe only with keys Stripe accepts', function (int $status, bool $connected) {
    $newsletter = paidNewsletter();
    $this->actingAs($newsletter->user);
    Http::fake(['api.stripe.com/v1/balance' => Http::response([], $status)]);

    $component = Livewire::test(PaidSubscriptions::class)
        ->call('edit', 'stripe')
        ->set('credentials.secret_key', 'sk_live_abc')
        ->call('connect');

    $connected ? $component->assertHasNoErrors() : $component->assertHasErrors('connection');

    expect($newsletter->paymentAccounts()->exists())->toBe($connected);
})->with([
    'accepted' => [200, true],
    'rejected' => [401, false],
]);

test('owners give and remove paid access by hand', function () {
    $newsletter = paidNewsletter();
    $this->actingAs($newsletter->user);

    $component = Livewire::test(PaidSubscriptions::class)
        ->set('grantEmail', 'friend@example.com')
        ->set('grantUntil', now()->addMonths(3)->toDateString())
        ->call('grantAccess')
        ->assertHasNoErrors();

    $subscriber = Subscriber::query()->where('email', 'friend@example.com')->sole();
    expect($subscriber->hasPaidAccess())->toBeTrue()->and($subscriber->paid_via)->toBe(PaymentProvider::Manual);

    $component->call('revokeAccess', $subscriber->id);

    expect($subscriber->fresh()->hasPaidAccess())->toBeFalse();
});

test('the paid subscriptions panel is forbidden without OTO 4', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::FrontEnd))->create();
    $this->actingAs($newsletter->user);

    Livewire::test(PaidSubscriptions::class)->assertForbidden();
});

test('only OTO 4 owners can mark an issue for paying readers', function (array $products, bool $saved) {
    $newsletter = Newsletter::factory()->for(buyer(Product::FrontEnd, ...$products))->create();
    $issue = Issue::factory()->for($newsletter)->create();
    $this->actingAs($newsletter->user);

    Livewire::test(Editor::class, ['issue' => $issue])->set('paidOnly', true);

    expect($issue->fresh()->is_paid_only)->toBe($saved);
})->with([
    'with OTO 4' => [[Product::Oto4Sponsor], true],
    'without OTO 4' => [[], false],
]);

test('a signed-in reader sees the owner\'s manual payment instructions and who to email after paying', function () {
    $newsletter = paidNewsletter();
    $newsletter->update([
        'paid_manual_enabled' => true,
        'paid_manual_instructions' => "USDT (TRC20): TXyz123\n<b>Bank</b>: 0123456789",
        'paid_manual_contact' => 'owner@example.com',
    ]);

    $this->withSession(['paid_reader.'.$newsletter->id => 'reader@example.com'])
        ->get(publicationUrl($newsletter, 'members'))
        ->assertOk()
        ->assertSee('How to pay')
        ->assertSee('$5.00 a month or $50.00 a year')
        ->assertSee('USDT (TRC20): TXyz123<br />', false)
        ->assertSee('&lt;b&gt;Bank&lt;/b&gt;', false)
        ->assertSee('href="mailto:owner@example.com?subject=', false)
        ->assertSee(rawurlencode('My email: reader@example.com'), false)
        ->assertDontSee('Pay with card')
        ->assertDontSee('not open yet');
});

test('subscriptions are not open when there is neither a payment account nor manual payment', function () {
    $newsletter = paidNewsletter();
    $newsletter->update(['paid_manual_enabled' => true, 'paid_manual_instructions' => null, 'paid_manual_contact' => 'owner@example.com']);

    $this->withSession(['paid_reader.'.$newsletter->id => 'reader@example.com'])
        ->get(publicationUrl($newsletter, 'members'))
        ->assertSee('Paid subscriptions are not open yet.')
        ->assertDontSee('owner@example.com');
});

test('owners need instructions and a contact email to turn on manual payments', function () {
    $newsletter = paidNewsletter();
    $this->actingAs($newsletter->user);

    Livewire::test(PaidSubscriptions::class)
        ->assertSet('manualContact', $newsletter->user->email)
        ->set('manualEnabled', true)
        ->set('manualContact', '')
        ->call('saveManualPayments')
        ->assertHasErrors(['manualInstructions', 'manualContact'])
        ->set('manualInstructions', 'Send bank transfer to 0123456789.')
        ->set('manualContact', 'owner@example.com')
        ->call('saveManualPayments')
        ->assertHasNoErrors();

    expect($newsletter->fresh()->acceptsManualPayments())->toBeTrue();
});

function manualNewsletter(): Newsletter
{
    $newsletter = paidNewsletter();
    $newsletter->update([
        'paid_manual_enabled' => true,
        'paid_manual_instructions' => 'USDT (TRC20): TXyz123',
        'paid_manual_contact' => 'owner@example.com',
    ]);

    return $newsletter;
}

test('a reader who says they paid is listed for the owner, who is emailed once', function () {
    Notification::fake();
    $newsletter = manualNewsletter();
    $session = ['paid_reader.'.$newsletter->id => 'reader@example.com'];

    $this->withSession($session)->post(publicationUrl($newsletter, 'members/paid'), ['plan' => 'yearly', 'reference' => 'tx-1'])
        ->assertRedirect(publicationUrl($newsletter, 'members'));
    $this->withSession($session)->post(publicationUrl($newsletter, 'members/paid'), ['plan' => 'yearly', 'reference' => 'tx-2']);

    $claim = PaymentClaim::query()->sole();
    expect($claim)->email->toBe('reader@example.com')->plan->toBe(PaidPlan::Yearly)->reference->toBe('tx-2')->status->toBe(PaymentClaimStatus::Pending);
    Notification::assertSentOnDemandTimes(ReaderPaymentClaimed::class, 1);
    Notification::assertSentOnDemand(ReaderPaymentClaimed::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'owner@example.com');

    $this->withSession($session)->get(publicationUrl($newsletter, 'members'))->assertSee('Waiting for '.$newsletter->name.' to confirm your payment');
});

test('readers cannot say they paid when manual payments are off', function () {
    $newsletter = paidNewsletter();

    $this->withSession(['paid_reader.'.$newsletter->id => 'reader@example.com'])
        ->post(publicationUrl($newsletter, 'members/paid'), ['plan' => 'monthly']);

    expect(PaymentClaim::query()->exists())->toBeFalse();
});

test('activating a claim gives one period of the plan, added after any access given by hand', function () {
    $this->freezeTime();
    $newsletter = manualNewsletter();
    $this->actingAs($newsletter->user);
    $newClaim = PaymentClaim::factory()->for($newsletter)->create(['email' => 'new@example.com', 'plan' => PaidPlan::Monthly]);
    payingReader($newsletter, 'renewing@example.com', now()->addDays(10));
    $renewalClaim = PaymentClaim::factory()->for($newsletter)->create(['email' => 'renewing@example.com', 'plan' => PaidPlan::Yearly]);

    Livewire::test(PaidSubscriptions::class)
        ->call('activateClaim', $newClaim->id)
        ->call('activateClaim', $renewalClaim->id);

    expect(Subscriber::query()->where('email', 'new@example.com')->sole()->paid_until->toDateTimeString())->toBe(now()->addMonth()->toDateTimeString())
        ->and(Subscriber::query()->where('email', 'renewing@example.com')->sole()->paid_until->toDateTimeString())->toBe(now()->addDays(10)->addYear()->toDateTimeString())
        ->and($newClaim->fresh()->status)->toBe(PaymentClaimStatus::Approved);
});

test('declining a claim gives no access', function () {
    $newsletter = manualNewsletter();
    $this->actingAs($newsletter->user);
    $claim = PaymentClaim::factory()->for($newsletter)->create(['email' => 'reader@example.com']);

    Livewire::test(PaidSubscriptions::class)->call('declineClaim', $claim->id);

    expect($claim->fresh()->status)->toBe(PaymentClaimStatus::Declined)
        ->and(Subscriber::query()->where('email', 'reader@example.com')->exists())->toBeFalse();
});

test('owners cannot act on another newsletter\'s claims', function () {
    $newsletter = manualNewsletter();
    $otherClaim = PaymentClaim::factory()->create();
    $this->actingAs($newsletter->user);

    Livewire::test(PaidSubscriptions::class)->call('activateClaim', $otherClaim->id)->assertNotFound();

    expect($otherClaim->fresh()->status)->toBe(PaymentClaimStatus::Pending);
});

test('the sign-in code email comes from the newsletter, with no sign of the platform', function () {
    config(['app.name' => 'NewsPub AI', 'mail.from.address' => 'mail@newspub.test']);
    $newsletter = manualNewsletter();
    $newsletter->update(['name' => 'Acme Money Notes']);

    $mail = (new ReaderSignInCode($newsletter, '482913'))->toMail(new AnonymousNotifiable);
    $html = (string) $mail->render();

    expect($mail->from)->toBe(['mail@newspub.test', 'Acme Money Notes'])
        ->and($mail->replyTo)->toBe([['owner@example.com', 'Acme Money Notes']])
        ->and($html)->toContain('482913')->toContain('Acme Money Notes')->not->toContain('NewsPub');
});
