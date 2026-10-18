<div class="space-y-6">
    <x-ui.page-header title="Social posts" description="Turn any issue into posts and video scripts that bring readers back to your page." icon="share" area="social" />

    @if ($issues->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="newspaper" area="social" title="Write an issue first" description="Social posts are made from your issues.">
                <x-ui.button :href="route('issues.create')" area="social" icon="sparkles">Write an issue</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <x-ui.select wire:model.live="issue" label="Issue" :options="$issues->all()" class="flex-1" />
                <x-ui.button wire:click="generateAll" area="social" icon="sparkles" loading="generateAll">Make all three</x-ui.button>
            </div>
        </x-ui.card>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($channels as $channel)
                <x-ui.card :title="$channel->label()" wire:key="channel-{{ $channel->value }}">
                    <x-slot:actions>
                        <x-ui.button variant="secondary" size="sm" wire:click="generate('{{ $channel->value }}')" loading="generate('{{ $channel->value }}')" icon="sparkles">Make one</x-ui.button>
                    </x-slot:actions>
                    @forelse ($posts->get($channel->value, collect()) as $post)
                        <div wire:key="post-{{ $post->id }}" class="mb-4 last:mb-0" x-data="{ copied: false }">
                            <pre class="max-h-80 overflow-y-auto rounded-control bg-subtle p-4 font-sans text-sm whitespace-pre-wrap text-ink">{{ $post->body }}</pre>
                            <div class="mt-2 flex gap-2">
                                <x-ui.button size="sm" area="social" icon="clipboard" x-on:click="navigator.clipboard.writeText(@js($post->body)); copied = true; setTimeout(() => copied = false, 2000)">
                                    <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                                </x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="deletePost({{ $post->id }})" icon="trash">Delete</x-ui.button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-muted">Press "Make one" and it appears here.</p>
                    @endforelse
                </x-ui.card>
            @endforeach
        </div>

        @if ($selected && ! $selected->isPublished())
            <x-ui.alert tone="warning">This issue is not live yet. Publish it before sharing posts, so the link in them works.</x-ui.alert>
        @endif
    @endif
</div>
