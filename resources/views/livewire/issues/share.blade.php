<div class="space-y-6">
    <section class="rounded-hero bg-grow px-6 py-10 text-white shadow-raised sm:px-10">
        <div class="flex items-center gap-3">
            <span class="inline-flex size-12 items-center justify-center rounded-full bg-white/15"><x-ui.icon name="check-circle" size="6" /></span>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Your issue is live!</h1>
        </div>
        <p class="mt-3 max-w-xl text-white/85">“{{ $issue->title }}” is on your page. Share the link below so people can read it and subscribe.</p>
    </section>

    <x-ui.card title="Your link">
        <x-ui.copy-field :value="$url" size="lg" />
        @php
            $encodedUrl = urlencode($url);
            $encodedText = urlencode($issue->title);
        @endphp
        <div class="mt-4 flex flex-wrap gap-2">
            <x-ui.button variant="secondary" :href="'https://twitter.com/intent/tweet?text='.$encodedText.'&url='.$encodedUrl" target="_blank" rel="noopener">Share on X</x-ui.button>
            <x-ui.button variant="secondary" :href="'https://www.linkedin.com/sharing/share-offsite/?url='.$encodedUrl" target="_blank" rel="noopener">LinkedIn</x-ui.button>
            <x-ui.button variant="secondary" :href="'https://www.facebook.com/sharer/sharer.php?u='.$encodedUrl" target="_blank" rel="noopener">Facebook</x-ui.button>
            <x-ui.button variant="secondary" :href="'https://wa.me/?text='.$encodedText.'%20'.$encodedUrl" target="_blank" rel="noopener">WhatsApp</x-ui.button>
            <x-ui.button variant="ghost" :href="$url" target="_blank" rel="noopener" icon="eye">Open my page</x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card title="What next?">
        <div class="grid gap-4 sm:grid-cols-3">
            <a href="{{ route('issues.create') }}" class="rounded-card border border-line p-4 transition hover:border-create hover:bg-create-tint">
                <x-ui.area-chip area="create" icon="sparkles" />
                <p class="mt-3 text-sm font-semibold text-ink">Write the next issue</p>
                <p class="text-sm text-muted">Keep a steady rhythm so readers come back.</p>
            </a>
            <a href="{{ route('integrations.index') }}" class="rounded-card border border-line p-4 transition hover:border-publish hover:bg-publish-tint">
                <x-ui.area-chip area="publish" :icon="$hasDelivery ? 'envelope' : 'lock-closed'" />
                <p class="mt-3 text-sm font-semibold text-ink">Send it by email</p>
                <p class="text-sm text-muted">{{ $hasDelivery ? 'Send this issue through your email service.' : 'Connect your email service to reach inboxes.' }}</p>
            </a>
            <a href="{{ $hasSocial ? route('social.index', ['issue' => $issue->id]) : route('social.index') }}" class="rounded-card border border-line p-4 transition hover:border-social hover:bg-social-tint">
                <x-ui.area-chip area="social" :icon="$hasSocial ? 'share' : 'lock-closed'" />
                <p class="mt-3 text-sm font-semibold text-ink">Turn it into posts</p>
                <p class="text-sm text-muted">Get a thread, a LinkedIn post and a video script.</p>
            </a>
        </div>
    </x-ui.card>

    <x-ui.card title="Your newsletter home page" description="Put this link in your bio so new readers can subscribe.">
        <x-ui.copy-field :value="$homeUrl" />
    </x-ui.card>
</div>
