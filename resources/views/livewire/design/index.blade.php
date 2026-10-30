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

    <x-ui.card title="Banner" description="A wide image across the top of your page. It does not appear in your emails.">
        <form wire:submit="saveBanner" class="space-y-4">
            <x-ui.field label="Banner image" for="banner" :error="$errors->first('banner')" hint="PNG, JPG or WEBP, under 2 MB. About 1600 by 400 pixels looks best.">
                <div class="space-y-3">
                    @if ($banner?->isPreviewable())
                        <img src="{{ $banner->temporaryUrl() }}" alt="New banner preview" class="max-h-40 w-full rounded border border-line object-cover">
                    @elseif (! empty($this->newsletter->branding['banner_path']))
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($this->newsletter->branding['banner_path']) }}" alt="Current banner" class="max-h-40 w-full rounded border border-line object-cover">
                    @endif
                    <input id="banner" type="file" wire:model="banner" accept="image/png,image/jpeg,image/webp" class="text-sm text-muted file:mr-3 file:min-h-11 file:rounded-control file:border-0 file:bg-subtle file:px-4 file:font-semibold file:text-ink" />
                </div>
            </x-ui.field>
            <div class="flex flex-wrap justify-end gap-3">
                @if (! empty($this->newsletter->branding['banner_path']) && ! $banner)
                    <x-ui.button variant="ghost" wire:click="removeBanner" wire:confirm="Remove the banner from your page?">Remove banner</x-ui.button>
                @endif
                <x-ui.button type="submit" area="create" loading="saveBanner">Save banner</x-ui.button>
            </div>
        </form>
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
                        @php $tls = $domain->tls_status; @endphp
                        <div class="rounded-card border border-line p-5" @if ($tls === \App\Enums\TlsStatus::Requested) wire:poll.15s @endif>
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm text-muted">Your address</p>
                                    <a href="https://{{ $domain->hostname }}" target="_blank" rel="noopener" class="font-mono text-base font-semibold break-all text-ink hover:text-create">{{ $domain->hostname }}</a>
                                    @if ($domain->is_apex)
                                        <p class="text-xs text-muted">www.{{ $domain->hostname }} forwards here.</p>
                                    @endif
                                </div>
                                <x-ui.pill tone="success">Connected</x-ui.pill>
                            </div>

                            <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-line pt-5">
                                <x-ui.icon :name="$tls === \App\Enums\TlsStatus::Active ? 'lock-closed' : ($tls === \App\Enums\TlsStatus::Failed ? 'warning' : 'clock')" class="text-muted" />
                                <span class="text-sm font-semibold text-ink">Secure connection (https)</span>
                                <x-ui.pill :tone="$tls->tone()" :pulse="$tls === \App\Enums\TlsStatus::Requested">{{ $tls->label() }}</x-ui.pill>
                            </div>

                            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                                @if ($domain->tls_issued_at)
                                    <div><dt class="text-muted">Secure since</dt><dd class="text-ink">{{ $domain->tls_issued_at->toFormattedDateString() }}</dd></div>
                                @endif
                                @if ($domain->tls_expires_at)
                                    <div><dt class="text-muted">Renews by</dt><dd class="text-ink">{{ $domain->tls_expires_at->toFormattedDateString() }}</dd></div>
                                @endif
                                <div><dt class="text-muted">Last checked</dt><dd class="text-ink">{{ $domain->tls_checked_at?->diffForHumans() ?? 'Not yet' }}</dd></div>
                            </dl>

                            @if ($tls === \App\Enums\TlsStatus::Requested)
                                <p class="mt-3 text-sm text-muted">We are getting your certificate. This page updates on its own; it usually takes a few minutes.</p>
                            @elseif ($tls === \App\Enums\TlsStatus::Active)
                                <p class="mt-3 text-sm text-muted">Readers see the padlock. Certificates renew automatically.</p>
                            @endif

                            @if ($domain->tls_error)
                                <x-ui.alert tone="danger" class="mt-4">{{ $domain->tls_error }}</x-ui.alert>
                            @endif

                            <div class="mt-5 flex flex-wrap gap-2">
                                <x-ui.button variant="secondary" size="sm" wire:click="checkCertificate" icon="retry" loading="checkCertificate">Check now</x-ui.button>
                                @if ($domain->canRequestCertificate())
                                    <x-ui.button size="sm" area="create" wire:click="requestCertificate" icon="lock-closed" loading="requestCertificate">Request again</x-ui.button>
                                @endif
                                <x-ui.button variant="ghost" size="sm" wire:click="removeDomain" wire:confirm="Disconnect {{ $domain->hostname }}? Your page goes back to your free address.">Remove</x-ui.button>
                            </div>
                        </div>
                    @else
                        @php
                            $records = $domain->setupRecords();
                            $checks = $domain->dns_checks ?? [];
                        @endphp
                        <x-ui.alert tone="warning" :title="count($records).' records to add'">
                            Sign in where you bought your domain (for example GoDaddy, Namecheap or Cloudflare), open the settings for
                            <strong>{{ \App\Support\Hostnames::registrable($domain->hostname) }}</strong>, and add these records. Then press "Check now".
                            @if ($domain->is_apex)
                                <span class="mt-2 block">{{ $domain->hostname }} becomes your main address, and www.{{ $domain->hostname }} forwards to it.</span>
                            @endif
                        </x-ui.alert>

                        @if ($domain->is_apex && $serverIpMissing)
                            <x-ui.alert tone="danger">Our server address is not set up yet, so this address cannot be connected right now. Please contact support.</x-ui.alert>
                        @endif

                        <ol class="space-y-4">
                            @foreach ($records as $record)
                                <li class="rounded-card border border-line p-4" wire:key="record-{{ $record['key'] }}">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-sm font-semibold text-ink">{{ $loop->iteration }}. {{ $record['purpose'] }}</p>
                                        @if (array_key_exists($record['key'], $checks))
                                            <x-ui.pill :tone="$checks[$record['key']] ? 'success' : 'danger'">{{ $checks[$record['key']] ? 'Found' : 'Not found yet' }}</x-ui.pill>
                                        @endif
                                    </div>
                                    <dl class="mt-3 grid gap-3 sm:grid-cols-[6rem_1fr_2fr]">
                                        <div><dt class="text-xs text-muted">Type</dt><dd class="font-mono text-sm text-ink">{{ $record['type'] }}</dd></div>
                                        <div><dt class="text-xs text-muted">Name (or Host)</dt><dd><x-ui.copy-field :value="$record['name']" /></dd></div>
                                        <div><dt class="text-xs text-muted">Value (or Points to)</dt><dd>
                                            @if ($record['value'])
                                                <x-ui.copy-field :value="$record['value']" />
                                            @else
                                                <span class="text-sm text-danger">Not available yet</span>
                                            @endif
                                        </dd></div>
                                    </dl>
                                    @if ($record['name'] === '@')
                                        <p class="mt-2 text-xs text-muted">"@" means the domain itself. Some providers want the name left empty instead. If there is already an A record for "@", change it to this value.</p>
                                    @endif
                                </li>
                            @endforeach
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
