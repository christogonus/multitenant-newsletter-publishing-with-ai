<div class="space-y-6">
    <x-ui.page-header title="Design" description="Choose how your page and emails look." icon="swatch" area="create" />

    <x-ui.card title="Templates">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($templates as $key => $template)
                @php $locked = $template['premium'] && ! $hasPremium; @endphp
                <button
                    type="button"
                    wire:key="template-{{ $key }}"
                    wire:click="chooseTemplate('{{ $key }}')"
                    @class([
                        'group relative overflow-hidden rounded-card border-2 text-left transition focus-visible:outline-2 focus-visible:outline-create',
                        'border-create' => $current === $key,
                        'border-line hover:border-create/50' => $current !== $key,
                    ])
                    aria-pressed="{{ $current === $key ? 'true' : 'false' }}"
                >
                    <div class="h-28 p-4" style="background: {{ $template['background'] }};">
                        <div class="h-full rounded-[6px] p-3 shadow-sm" style="background: {{ $template['surface'] }};">
                            <div class="h-2.5 w-1/2 rounded-full" style="background: {{ $template['accent'] }};"></div>
                            <div class="mt-2 h-2 w-full rounded-full opacity-30" style="background: {{ $template['text'] }};"></div>
                            <div class="mt-1.5 h-2 w-4/5 rounded-full opacity-30" style="background: {{ $template['text'] }};"></div>
                            <div class="mt-1.5 h-2 w-3/5 rounded-full opacity-30" style="background: {{ $template['text'] }};"></div>
                        </div>
                    </div>
                    <div class="flex items-start gap-2 bg-card p-4">
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 text-sm font-semibold text-ink">
                                {{ $template['name'] }}
                                @if ($template['premium'])<x-ui.badge tone="create" :icon="$locked ? 'lock-closed' : null">Premium</x-ui.badge>@endif
                            </p>
                            <p class="mt-0.5 text-xs text-muted">{{ $template['description'] }}</p>
                        </div>
                        @if ($current === $key)
                            <x-ui.icon name="check-circle" class="text-create" />
                        @endif
                    </div>
                </button>
            @endforeach
        </div>
    </x-ui.card>

    @if (! $hasPremium)
        <x-ui.locked-feature :product="$premiumProduct">
            Unlock 4 premium designs, your own logo, colours and fonts, and your own web address such as news.yourname.com.
        </x-ui.locked-feature>
    @else
        <x-ui.card title="Your brand" description="Your logo, colour and font appear on your page and in your emails.">
            <form wire:submit="saveBranding" class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Accent colour" for="accent" :error="$errors->first('accentColor')">
                    <div class="flex items-center gap-3">
                        <input id="accent" type="color" wire:model="accentColor" class="h-11 w-16 cursor-pointer rounded-control border border-line bg-card p-1" />
                        <span class="font-mono text-sm text-muted">{{ $accentColor }}</span>
                    </div>
                </x-ui.field>
                <x-ui.select wire:model="font" label="Font" :options="$fonts" placeholder="Use the template's font" />
                <x-ui.field label="Logo" for="logo" :error="$errors->first('logo')" hint="PNG, JPG or WEBP, under 1 MB." class="sm:col-span-2">
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($logo)
                            <img src="{{ $logo->temporaryUrl() }}" alt="New logo preview" class="h-12 rounded border border-line bg-white p-1">
                        @elseif (! empty($this->newsletter->branding['logo_path']))
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($this->newsletter->branding['logo_path']) }}" alt="Current logo" class="h-12 rounded border border-line bg-white p-1">
                            <x-ui.button variant="ghost" size="sm" wire:click="removeLogo">Remove</x-ui.button>
                        @endif
                        <input id="logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="text-sm text-muted file:mr-3 file:min-h-11 file:rounded-control file:border-0 file:bg-subtle file:px-4 file:font-semibold file:text-ink" />
                    </div>
                </x-ui.field>
                <div class="sm:col-span-2 flex justify-end">
                    <x-ui.button type="submit" area="create" loading="saveBranding">Save brand</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Your own web address" description="Use an address you own, like news.yourname.com.">
            <form wire:submit="saveDomain" class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <x-ui.input wire:model="hostname" label="Address" placeholder="news.yourname.com" class="flex-1" />
                <x-ui.button type="submit" variant="secondary" class="sm:mt-7" loading="saveDomain">Save address</x-ui.button>
            </form>

            @if ($domain)
                <div class="mt-6 space-y-4">
                    @if ($domain->verified_at)
                        <x-ui.alert tone="success" title="Connected">
                            {{ $domain->hostname }} now shows your newsletter.
                            <x-slot:actions><x-ui.button variant="ghost" size="sm" wire:click="removeDomain">Remove</x-ui.button></x-slot:actions>
                        </x-ui.alert>
                    @else
                        <x-ui.alert tone="warning" title="Two steps to finish">
                            Sign in where you bought your domain (for example GoDaddy, Namecheap or Cloudflare), open the settings for <strong>{{ $domain->hostname }}</strong>, and add these two records.
                        </x-ui.alert>
                        <ol class="space-y-4">
                            <li class="rounded-card border border-line p-4">
                                <p class="text-sm font-semibold text-ink">1. Point your address to us</p>
                                <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div><dt class="text-xs text-muted">Type</dt><dd class="font-mono text-sm">CNAME</dd></div>
                                    <div><dt class="text-xs text-muted">Name</dt><dd><x-ui.copy-field :value="explode('.', $domain->hostname)[0]" /></dd></div>
                                    <div><dt class="text-xs text-muted">Value</dt><dd><x-ui.copy-field :value="$pointTo" /></dd></div>
                                </dl>
                            </li>
                            <li class="rounded-card border border-line p-4">
                                <p class="text-sm font-semibold text-ink">2. Prove it is yours</p>
                                <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div><dt class="text-xs text-muted">Type</dt><dd class="font-mono text-sm">TXT</dd></div>
                                    <div><dt class="text-xs text-muted">Name</dt><dd><x-ui.copy-field :value="'_newspub.'.explode('.', $domain->hostname)[0]" /></dd></div>
                                    <div><dt class="text-xs text-muted">Value</dt><dd><x-ui.copy-field :value="$domain->verificationRecordValue()" /></dd></div>
                                </dl>
                            </li>
                        </ol>
                        <div class="flex flex-wrap gap-2">
                            <x-ui.button wire:click="verifyDomain" area="create" icon="retry" loading="verifyDomain">Check now</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="removeDomain">Remove</x-ui.button>
                        </div>
                        @if ($domain->last_checked_at)
                            <p class="text-sm text-muted">Last checked {{ $domain->last_checked_at->diffForHumans() }}.</p>
                        @endif
                    @endif
                </div>
            @endif
        </x-ui.card>
    @endif
</div>
