<div class="space-y-6">
    <x-ui.page-header title="Reseller" description="Sell NewsPub AI on your own checkout. Each code you sell gives one buyer a full account." icon="ticket" area="earn" />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Codes left to create" :value="$remaining.' of '.$quota" icon="ticket" area="earn" />
        <x-ui.stat-card label="Codes created" :value="$used" icon="stack" area="earn" />
        <x-ui.stat-card label="Codes used by buyers" :value="$redeemed" icon="users" area="grow" />
    </div>

    <div role="tablist" class="flex gap-1 overflow-x-auto rounded-control bg-subtle p-1 sm:w-fit">
        @foreach (['codes' => 'Codes', 'page' => 'Your sign-up page', 'assets' => 'Sales materials'] as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                @class(['min-h-11 rounded-[6px] px-4 text-sm font-semibold whitespace-nowrap', 'bg-card text-ink shadow-card' => $tab === $key, 'text-muted hover:text-ink' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'codes')
        <x-ui.card title="Create codes" description="Give one code to each buyer after they pay on your checkout.">
            <form wire:submit="generate" class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <x-ui.input wire:model="quantity" type="number" min="1" max="100" label="How many?" class="sm:w-40" />
                <x-ui.button type="submit" area="earn" class="sm:mt-7" icon="plus" loading="generate" :disabled="$remaining < 1">Create codes</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.table :headers="['Code', 'Status', 'Used by', '']" :empty="$codes->isEmpty()">
            <x-slot:emptyState>
                <x-ui.empty-state icon="ticket" area="earn" title="No codes yet" description="Create a batch of codes to start selling." />
            </x-slot:emptyState>
            @foreach ($codes as $code)
                <tr wire:key="code-{{ $code->id }}">
                    <td class="px-4 py-3 font-mono text-sm text-ink">{{ $code->code }}</td>
                    <td class="px-4 py-3">
                        @if ($code->revoked_at)
                            <x-ui.pill tone="neutral">Cancelled</x-ui.pill>
                        @elseif ($code->redeemed_at)
                            <x-ui.pill tone="success">Used {{ $code->redeemed_at->toFormattedDateString() }}</x-ui.pill>
                        @else
                            <x-ui.pill tone="warning">Ready to sell</x-ui.pill>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-muted">{{ $code->redeemer?->email ?? '—' }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @if ($code->isAvailable())
                            <x-ui.button variant="ghost" size="sm" x-data x-on:click="navigator.clipboard.writeText(@js($code->code))" icon="clipboard">Copy</x-ui.button>
                            <x-ui.button variant="ghost" size="sm" wire:click="revoke({{ $code->id }})" wire:confirm="Cancel this code? It will stop working.">Cancel</x-ui.button>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if ($codes->hasPages())
                <x-slot:footer>{{ $codes->links() }}</x-slot:footer>
            @endif
        </x-ui.table>
    @elseif ($tab === 'page')
        <x-ui.card title="Your sign-up page" description="Send buyers here to activate their code. It shows your brand name.">
            <form wire:submit="saveBrand" class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="brand" label="Brand name buyers see" placeholder="For example: Inbox Pro" />
                <x-ui.input wire:model="slug" label="Link name" hint="Letters, numbers and dashes." />
                <div class="sm:col-span-2 flex justify-end"><x-ui.button type="submit" area="earn" loading="saveBrand">Save</x-ui.button></div>
            </form>
            @if ($signupUrl)
                <div class="mt-5"><x-ui.copy-field :value="$signupUrl" label="Your activation link" size="lg" /></div>
            @endif
        </x-ui.card>
    @else
        <x-ui.card title="Rules">
            <ul class="space-y-2 text-sm text-ink">
                @foreach ($assets['rules'] ?? [] as $rule)
                    <li class="flex gap-2"><x-ui.icon name="info" size="4" class="mt-0.5 text-earn" />{{ $rule }}</li>
                @endforeach
            </ul>
        </x-ui.card>

        @if (! empty($assets['sales_page']))
            <x-ui.card title="Sales page copy">
                <div class="space-y-3 text-sm">
                    <x-ui.copy-field :value="$assets['sales_page']['headline']" label="Headline" />
                    <x-ui.copy-field :value="$assets['sales_page']['subheadline']" label="Sub-headline" />
                    <div>
                        <p class="mb-1.5 font-medium text-ink">Benefits</p>
                        <ul class="list-inside list-disc text-muted">@foreach ($assets['sales_page']['bullets'] as $bullet)<li>{{ $bullet }}</li>@endforeach</ul>
                    </div>
                    <div>
                        <p class="mb-1.5 font-medium text-ink">Questions and answers</p>
                        <dl class="space-y-2">@foreach ($assets['sales_page']['faq'] as $faq)<div><dt class="font-semibold text-ink">{{ $faq['q'] }}</dt><dd class="text-muted">{{ $faq['a'] }}</dd></div>@endforeach</dl>
                    </div>
                </div>
            </x-ui.card>
        @endif

        @foreach ($assets['emails'] ?? [] as $email)
            <x-ui.card :title="$email['title']" wire:key="asset-email-{{ $loop->index }}">
                <x-ui.copy-field :value="$email['subject']" label="Subject" />
                <pre class="mt-4 rounded-control bg-subtle p-4 font-sans text-sm whitespace-pre-wrap text-ink">{{ $email['body'] }}</pre>
            </x-ui.card>
        @endforeach

        @if (! empty($assets['social_posts']))
            <x-ui.card title="Social posts">
                <div class="space-y-3">
                    @foreach ($assets['social_posts'] as $post)
                        <x-ui.copy-field :value="$post" />
                    @endforeach
                </div>
            </x-ui.card>
        @endif
    @endif
</div>
