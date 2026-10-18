<x-layouts::auth title="Privacy">
    <x-slot:heading>Privacy</x-slot:heading>
    <x-slot:subheading>Plain-language summary. Have a lawyer review the final version before launch.</x-slot:subheading>

    <div class="space-y-4 text-sm text-ink">
        <p><strong>What we keep about readers.</strong> Email address, optional first name, where and when they signed up, and the network address they signed up from (as proof of consent).</p>
        <p><strong>Who sees it.</strong> The newsletter's owner, and the email service the owner connects to send issues.</p>
        <p><strong>Leaving.</strong> Readers can unsubscribe or delete their details at any time from the link on their referral page or by asking the newsletter's owner.</p>
        <p><strong>Account holders.</strong> We keep your name, email, purchases and the content you create. We use an AI provider to write content; your newsletter details are sent to it only to write your issues.</p>
        <p><strong>Contact.</strong> {{ config('newspub.support_email') }}</p>
    </div>
</x-layouts::auth>
