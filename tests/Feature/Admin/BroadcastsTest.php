<?php

use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Livewire\Admin\Broadcasts;
use App\Models\Broadcast;
use App\Models\User;
use App\Notifications\BroadcastEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\Mime\Email;

function writeBroadcast(): Testable
{
    return Livewire::test(Broadcasts::class)
        ->set('fromName', 'Sam from NewsletterPub')
        ->set('fromEmail', 'sam@newsletterpub.example')
        ->set('subject', 'Hello {first_name}')
        ->set('body', 'Big news this week.');
}

test('owners of a plan include anyone whose plan grants it, minus left-out owners, demo, suspended and unsubscribed accounts', function () {
    $silver = buyer(Product::Oto2Silver);
    $gold = buyer(Product::Oto2Gold);
    $silverWithSocial = buyer(Product::Oto2Silver, Product::Oto6Social);
    $frontEndOnly = buyer();
    $demo = buyer(Product::Oto2Silver);
    $demo->forceFill(['is_demo' => true])->save();
    buyer(Product::Oto2Silver)->forceFill(['suspended_at' => now()])->save();
    buyer(Product::Oto2Silver)->forceFill(['emails_unsubscribed_at' => now()])->save();
    $refunded = buyer(Product::Oto2Silver);
    $sale = $refunded->purchases()->where('product_key', Product::Oto2Silver)->sole();
    $refunded->purchases()->create([...$sale->only('source', 'transaction_id', 'product_key', 'amount', 'payload'), 'action' => PurchaseAction::Refund]);

    $audience = ['mode' => 'owners', 'items' => ['oto2_silver'], 'exclude_items' => ['oto6_social'], 'include_demo' => false];

    expect(Broadcast::recipientsFor($audience)->pluck('id')->sort()->values()->all())->toBe([$silver->id, $gold->id])
        ->and(Broadcast::recipientsFor([...$audience, 'include_demo' => true])->pluck('id')->all())->toContain($demo->id)
        ->and(Broadcast::recipientsFor(['mode' => 'everyone'])->pluck('id')->all())->toContain($frontEndOnly->id, $silverWithSocial->id)->not->toContain($demo->id);
});

test('an admin sends a broadcast that reaches each recipient from the chosen sender with a one-click unsubscribe', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $gold = buyer(Product::Oto2Gold);
    $frontEndOnly = buyer();
    $this->actingAs($admin);

    writeBroadcast()->set('mode', 'owners')->set('items', ['oto2_silver'])->call('send')->assertHasNoErrors();

    $broadcast = Broadcast::sole();
    expect($broadcast->recipients_count)->toBe(1)->and($broadcast->sent_at)->not->toBeNull();
    Notification::assertNotSentTo($frontEndOnly, BroadcastEmail::class);
    Notification::assertSentTo($gold, BroadcastEmail::class, function (BroadcastEmail $notification) use ($gold) {
        $mail = $notification->toMail($gold);
        $email = new Email;
        foreach ($mail->callbacks as $callback) {
            $callback($email);
        }

        return $mail->from === ['sam@newsletterpub.example', 'Sam from NewsletterPub']
            && $mail->replyTo === [['sam@newsletterpub.example', 'Sam from NewsletterPub']]
            && $mail->subject === 'Hello '.strtok($gold->name, ' ')
            && str_contains($email->getHeaders()->get('List-Unsubscribe')->getBodyAsString(), '/emails/unsubscribe/'.$gold->id)
            && $email->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString() === 'List-Unsubscribe=One-Click';
    });
});

test('a test email goes only to the admin and nothing is recorded', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $customer = buyer();
    $this->actingAs($admin);

    writeBroadcast()->call('sendTest')->assertHasNoErrors();

    Notification::assertSentTo($admin, BroadcastEmail::class, function (BroadcastEmail $notification) use ($admin) {
        $html = (string) $notification->toMail($admin)->render();

        return str_contains($html, 'Big news this week.') && str_contains($html, 'Unsubscribe from these emails');
    });
    Notification::assertNotSentTo($customer, BroadcastEmail::class);
    expect(Broadcast::count())->toBe(0);
});

test('a broadcast needs a sender, subject, message and, for owners, at least one item', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Broadcasts::class)
        ->set('fromEmail', 'not-an-email')
        ->set('subject', '')
        ->set('body', '')
        ->set('mode', 'owners')
        ->set('buttonLabel', 'Open')
        ->set('buttonUrl', '')
        ->call('send')
        ->assertHasErrors(['fromEmail' => 'email', 'subject' => 'required', 'body' => 'required', 'items' => 'required', 'buttonUrl' => 'required_with']);

    expect(Broadcast::count())->toBe(0);
});

test('buyers cannot open or send broadcasts', function () {
    $this->actingAs(buyer());

    $this->get(route('admin.broadcasts'))->assertForbidden();
    writeBroadcast()->call('send')->assertForbidden();
});

test('the unsubscribe link asks first, and one-click mail clients unsubscribe straight away', function () {
    $customer = buyer();
    $url = BroadcastEmail::unsubscribeUrl($customer);

    $this->get($url)->assertOk()->assertSee('Unsubscribe from announcements?');
    expect($customer->fresh()->emails_unsubscribed_at)->toBeNull();

    $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();
    expect($customer->fresh()->emails_unsubscribed_at)->not->toBeNull();

    $this->get($url)->assertOk()->assertSee('You are unsubscribed');
});

test('unsubscribe links cannot be forged', function () {
    $customer = buyer();

    $this->post(appUrl('emails/unsubscribe/'.$customer->id))->assertForbidden();

    expect($customer->fresh()->emails_unsubscribed_at)->toBeNull();
});

test('the message starts from an editable sample, and the preview shows the rendered email', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'Ada Admin']));

    Livewire::test(Broadcasts::class)
        ->assertSet('subject', fn (string $subject) => str_contains($subject, '{first_name}'))
        ->assertSet('buttonUrl', route('dashboard'))
        ->call('useSample', 'upgrade')
        ->assertSet('buttonUrl', route('upgrades'))
        ->set('body', 'Hi {first_name}, **big news**.')
        ->set('previewing', true)
        ->assertSeeHtml('srcdoc=')
        ->assertSee('Hi Ada,')
        ->assertSee('See the upgrade');
});

test('the trend radar notice asks for each monthly edition from Edition 2 until its email is sent', function () {
    Notification::fake();
    config(['newspub.trend_radar.starts' => '2026-11-01']);
    $this->actingAs(User::factory()->admin()->create());
    buyer();

    $this->travelTo('2026-11-20');
    $this->get(route('admin.users'))->assertOk()->assertDontSee('Trend Radar Edition');

    $this->travelTo('2026-12-03');
    $this->get(route('admin.users'))->assertSee('Trend Radar Edition 2 (December 2026) is due')
        ->assertSee(route('admin.broadcasts', ['sample' => 'trend_radar']), false);

    Livewire::withQueryParams(['sample' => 'trend_radar'])->test(Broadcasts::class)
        ->assertSet('subject', 'Your Newsletter Trend Radar: Edition 2 is here')
        ->assertSet('tag', 'trend-radar-2')
        ->set('buttonUrl', 'https://bonuses.example.com/trend-radar-2.pdf')
        ->call('send')
        ->assertHasNoErrors();

    expect(Broadcast::sole()->tag)->toBe('trend-radar-2');
    $this->get(route('admin.users'))->assertDontSee('Trend Radar Edition 2');

    $this->travelTo('2027-01-04');
    $this->get(route('admin.users'))->assertSee('Trend Radar Edition 3 (January 2027) is due');

    $this->travelTo('2027-11-01');
    $this->get(route('admin.users'))->assertDontSee('Trend Radar Edition');
});
