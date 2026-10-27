<?php

namespace App\Livewire\Admin;

use App\Enums\Product;
use App\Jobs\SendBroadcast;
use App\Models\Broadcast;
use App\Models\BumpOffer;
use App\Notifications\BroadcastEmail;
use App\Support\TrendRadar;
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

    /**
     * Whether the Preview tab (the rendered email) is showing instead of the editor.
     */
    public bool $previewing = false;

    /**
     * Marks what a broadcast delivers, such as "trend-radar-3", so reminders know it went out.
     */
    public ?string $tag = null;

    public function mount(): void
    {
        $this->fromName = (string) config('mail.from.name');
        $this->fromEmail = (string) config('mail.from.address');
        $this->useSample(request()->query('sample') === 'trend_radar' ? 'trend_radar' : 'announcement');
    }

    /**
     * Fills the message with a ready-made email to edit.
     */
    public function useSample(string $key): void
    {
        $sample = $this->samples()[$key] ?? null;

        if (! $sample) {
            return;
        }

        $this->subject = $sample['subject'];
        $this->body = $sample['body'];
        $this->buttonLabel = $sample['button_label'];
        $this->buttonUrl = $sample['button_url'];
        $this->tag = $sample['tag'] ?? null;
        $this->previewing = false;
        $this->resetValidation();
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

        $this->useSample('announcement');
        $this->dispatch('toast', message: 'Sending now. Emails go out over the next few minutes.', tone: 'success');
    }

    public function render(): View
    {
        $products = collect(Product::cases())->mapWithKeys(fn (Product $product) => [$product->value => $product->label()]);
        $bumps = BumpOffer::query()->get()->mapWithKeys(fn (BumpOffer $bumpOffer) => ['bump:'.$bumpOffer->id => 'Order bump: '.$bumpOffer->name]);

        return view('livewire.admin.broadcasts', [
            'samples' => collect($this->samples())->map(fn (array $sample) => $sample['label'])->all(),
            'previewHtml' => $this->previewing ? (string) (new BroadcastEmail($this->draft()))->toMail(auth()->user())->render() : null,
            'itemOptions' => $products->union($bumps)->all(),
            'recipientCount' => Broadcast::recipientsFor($this->audience())->count(),
            'history' => Broadcast::query()->with('sender')->latest()->limit(20)->get(),
        ]);
    }

    /**
     * Starting points for common emails. {first_name} is filled in for each customer.
     *
     * @return array<string, array{label: string, subject: string, body: string, button_label: string, button_url: string, tag?: string}>
     */
    private function samples(): array
    {
        $appName = config('app.name');
        $trendRadar = app(TrendRadar::class);
        $edition = $trendRadar->currentEdition();

        return [
            ...($edition >= 2 ? ['trend_radar' => [
                'label' => "Trend Radar Edition {$edition}",
                'subject' => "Your Newsletter Trend Radar: Edition {$edition} is here",
                'body' => <<<MARKDOWN
                    Hi {first_name},

                    Edition {$edition} of **The Newsletter Trend Radar** is ready for you. This is your {$trendRadar->month($edition)} briefing on what's shifting in the newsletter world, and what to do about it.

                    Inside this edition:

                    - **The big shift:** [one line on this month's biggest change]
                    - **Platform watch:** what's new at beehiiv, Ghost, Kit and the inbox providers
                    - **Sponsor market:** [one line on where sponsor money is moving]
                    - **Niche heat map:** the niches heating up, holding steady and cooling off

                    Each section ends with one move to make now. Read those first if you're short on time.

                    The {$appName} team
                    MARKDOWN,
                'button_label' => "Read Edition {$edition}",
                'button_url' => '',
                'tag' => TrendRadar::tag($edition),
            ]] : []),
            'announcement' => [
                'label' => 'Announcement',
                'subject' => "New in {$appName}: {first_name}, this one saves you time",
                'body' => <<<MARKDOWN
                    Hi {first_name},

                    Quick update from the {$appName} team. We have just added something you asked for:

                    **[Name of the new feature]** lets you [what it does, in one sentence].

                    Here is how to try it in under a minute:

                    1. Sign in and open **[where to find it]**.
                    2. [The one thing to press or choose].
                    3. That's it. [What happens next].

                    Reply to this email and tell us what you think. We read every reply.

                    Thanks for building with us,
                    The {$appName} team
                    MARKDOWN,
                'button_label' => 'Try it now',
                'button_url' => route('dashboard'),
            ],
            'tip' => [
                'label' => 'Weekly tip',
                'subject' => '{first_name}, a 2-minute trick to grow your newsletter',
                'body' => <<<MARKDOWN
                    Hi {first_name},

                    Here is this week's tip for getting more from {$appName}.

                    ## [The tip in a few words]

                    [Explain the tip in two or three short sentences. Why it works, and who it helps.]

                    - **Do this:** [the simple action]
                    - **Why:** [the result they can expect]
                    - **Time needed:** about 2 minutes

                    Give it a go before your next newsletter goes out.

                    The {$appName} team
                    MARKDOWN,
                'button_label' => 'Open my dashboard',
                'button_url' => route('dashboard'),
            ],
            'upgrade' => [
                'label' => 'Upgrade offer',
                'subject' => '{first_name}, unlock more for your newsletters',
                'body' => <<<MARKDOWN
                    Hi {first_name},

                    You have been publishing with {$appName}, and we think you are ready for the next step.

                    **[Upgrade name]** adds:

                    - [Benefit one]
                    - [Benefit two]
                    - [Benefit three]

                    It is a one-time price, and it works with everything you have already set up.

                    [Add a deadline or bonus here if there is one.]

                    The {$appName} team
                    MARKDOWN,
                'button_label' => 'See the upgrade',
                'button_url' => route('upgrades'),
            ],
        ];
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
            'tag' => $this->tag,
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
