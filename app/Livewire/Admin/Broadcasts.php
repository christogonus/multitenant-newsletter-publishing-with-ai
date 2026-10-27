<?php

namespace App\Livewire\Admin;

use App\Enums\Product;
use App\Jobs\SendBroadcast;
use App\Models\Broadcast;
use App\Models\BumpOffer;
use App\Notifications\BroadcastEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Admin: email a group of customers, such as everyone, or owners of some plans who don't yet have an upgrade.
 * Deliberately small: one message, one optional button, sent once.
 */
#[Title('Admin · Emails')]
class Broadcasts extends Component
{
    public string $fromName = '';

    public string $fromEmail = '';

    public string $subject = '';

    public string $body = '';

    public string $buttonLabel = '';

    public string $buttonUrl = '';

    /**
     * "everyone" or "owners".
     */
    public string $mode = 'everyone';

    /** @var list<string> */
    public array $items = [];

    /** @var list<string> */
    public array $excludeItems = [];

    public bool $includeDemo = false;

    public function mount(): void
    {
        $this->fromName = (string) config('mail.from.name');
        $this->fromEmail = (string) config('mail.from.address');
    }

    public function sendTest(): void
    {
        $this->authorizeAdmin();
        $this->validateMessage();

        Notification::sendNow(auth()->user(), new BroadcastEmail($this->draft()));

        $this->dispatch('toast', message: 'Test sent to '.auth()->user()->email.'.', tone: 'success');
    }

    public function send(): void
    {
        $this->authorizeAdmin();
        $this->validateMessage();

        if (Broadcast::recipientsFor($this->audience())->doesntExist()) {
            $this->addError('items', 'Nobody matches this audience.');

            return;
        }

        $broadcast = $this->draft();
        $broadcast->save();
        SendBroadcast::dispatch($broadcast);

        $this->reset('subject', 'body', 'buttonLabel', 'buttonUrl');
        $this->dispatch('toast', message: 'Sending now. Emails go out over the next few minutes.', tone: 'success');
    }

    public function render(): View
    {
        $products = collect(Product::cases())->mapWithKeys(fn (Product $product) => [$product->value => $product->label()]);
        $bumps = BumpOffer::query()->get()->mapWithKeys(fn (BumpOffer $bumpOffer) => ['bump:'.$bumpOffer->id => 'Order bump: '.$bumpOffer->name]);

        return view('livewire.admin.broadcasts', [
            'itemOptions' => $products->union($bumps)->all(),
            'recipientCount' => Broadcast::recipientsFor($this->audience())->count(),
            'history' => Broadcast::query()->with('sender')->latest()->limit(20)->get(),
        ]);
    }

    /**
     * @return array{mode: string, items: list<string>, exclude_items: list<string>, include_demo: bool}
     */
    private function audience(): array
    {
        return [
            'mode' => $this->mode === 'owners' ? 'owners' : 'everyone',
            'items' => array_values($this->items),
            'exclude_items' => array_values($this->excludeItems),
            'include_demo' => $this->includeDemo,
        ];
    }

    private function draft(): Broadcast
    {
        return new Broadcast([
            'sent_by' => auth()->id(),
            'from_name' => $this->fromName,
            'from_email' => $this->fromEmail,
            'subject' => $this->subject,
            'body' => $this->body,
            'button_label' => $this->buttonLabel ?: null,
            'button_url' => $this->buttonUrl ?: null,
            'audience' => $this->audience(),
        ]);
    }

    private function validateMessage(): void
    {
        $this->validate([
            'fromName' => ['required', 'string', 'max:100'],
            'fromEmail' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:20000'],
            'buttonLabel' => ['nullable', 'required_with:buttonUrl', 'string', 'max:60'],
            'buttonUrl' => ['nullable', 'required_with:buttonLabel', 'url:https,http', 'max:2048'],
            'mode' => ['required', Rule::in(['everyone', 'owners'])],
            'items' => [Rule::requiredIf($this->mode === 'owners'), 'array'],
        ], [
            'items.required' => 'Pick at least one plan, upgrade or order bump.',
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }
}
