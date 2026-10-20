@php
    $supportEmail = config('newspub.support_email');

    $faqs = [
        'I did not get the welcome email' => 'Check your spam, junk and promotions folders, and add '.config('mail.from.address').' to your contacts. Some email providers block messages from new senders. You do not need the email to get started: your login details are always at '.route('access').'.',
        'My password does not work' => 'If you have already changed your password, use the one you chose. Forgotten it? Use "Forgot password" on the sign-in page and we will email you a link to choose a new one.',
        'How do I get my first issue out?' => 'Press "Write a new issue" on Home. The AI writes it in about a minute. Read it, change anything you like, then press Publish. You get a link to share straight away.',
        'Where do my readers sign up?' => 'Every newsletter has its own page with a sign-up box. Find the link under "View my page" at the top of the screen. Share it in your bio, in posts and with friends.',
        'Do my readers get emails?' => 'Your page shows every issue. To send issues to inboxes, connect an email service such as beehiiv, Kit or Mailchimp from "Email services". That service sends the emails and asks new readers to confirm they want them.',
        'How many newsletters and issues can I have?' => 'You can run up to 3 newsletters, and write 15 issues for each one every 7 days. Issues free up again as the week rolls on. Unlimited Creation removes both limits.',
        'Can I change what the AI wrote?' => 'Yes. Every heading, paragraph and list is editable. Drag the handle to reorder. Changes save on their own.',
        'Something says "Needs a retry"' => 'Sometimes the writer cannot finish. Open the issue and press "Try again". Failed attempts never count toward your weekly issues.',
        'I bought an upgrade but it is still locked' => 'Upgrades unlock within a minute of payment. Refresh the page. If it is still locked after 10 minutes, email us with your receipt.',
        'How do I get a refund?' => 'Refunds are handled by the store you bought from. Once refunded, the matching feature is switched off automatically.',
    ];
@endphp

<x-ui.card title="Get started in 10 minutes" description="Follow these steps the first time you sign in.">
    <ol class="space-y-4">
        @foreach (\App\Support\GettingStarted::steps() as $step)
            <li class="flex gap-3">
                <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">{{ $loop->iteration }}</span>
                <div>
                    <p class="text-sm font-semibold text-ink">{{ $step['title'] }}</p>
                    <p class="mt-0.5 text-sm text-muted">{{ $step['body'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</x-ui.card>

<x-ui.card title="Common questions">
    <div class="divide-y divide-line">
        @foreach ($faqs as $question => $answer)
            <details class="group py-3">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 text-sm font-semibold text-ink">
                    {{ $question }}
                    <x-ui.icon name="chevron-down" size="4" class="text-muted transition group-open:rotate-180" />
                </summary>
                <p class="pt-2 text-sm text-muted">{{ $answer }}</p>
            </details>
        @endforeach
    </div>
</x-ui.card>

<x-ui.card title="Still stuck?">
    <p class="text-sm text-muted">Email us from the address you bought with, and tell us what you tried. We reply within one working day.</p>
    <x-ui.button class="mt-4" :href="'mailto:'.$supportEmail" icon="envelope">Email support</x-ui.button>
</x-ui.card>
