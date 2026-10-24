<x-layouts::auth title="Terms of use">
    <x-slot:heading>Terms of use</x-slot:heading>
    <x-slot:subheading>Plain-language summary. Have a lawyer review the final version before launch.</x-slot:subheading>

    <div class="space-y-4 text-sm text-ink">
        <p><strong>Your account.</strong> Your purchase gives you lifetime access to the features you bought, for as long as the service runs. Refunds and chargebacks switch the matching features off.</p>
        <p><strong>Fair use.</strong> Your plan includes a set number of AI-written issues each week. "Unlimited" plans are subject to a fair-use limit that stops automated or abusive use; normal publishing never comes close to it.</p>
        <p><strong>Your content.</strong> You own what you publish and you are responsible for it. Check AI-written content before you publish it. Do not publish anything illegal, hateful, misleading or that copies other people's work.</p>
        <p><strong>Your readers.</strong> Only collect sign-ups from people who asked to hear from you. {{ config('app.name') }} stores each reader's sign-up time and address as proof. {{ config('app.name') }} does not send emails to your readers; the email service you connect does, and it should ask new readers to confirm their sign-up.</p>
        <p><strong>Reports.</strong> Anyone can report a page. We may take pages offline that break these terms.</p>
        <p><strong>Resellers and agencies.</strong> Reseller and agency licences let you sell accounts or services. They do not include the source code or the right to host the software yourself.</p>
    </div>
</x-layouts::auth>
