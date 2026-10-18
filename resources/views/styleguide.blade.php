<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    @include('partials.head', ['title' => 'Styleguide'])
</head>
<body class="min-h-full font-sans">
    @php $areas = \App\Enums\Area::cases(); @endphp
    <div class="mx-auto max-w-6xl space-y-10 px-4 py-10 sm:px-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-display tracking-tight text-ink">Component kit</h1>
                <p class="text-muted">Every component in every state. Local only.</p>
            </div>
            <x-ui.theme-toggle />
        </div>

        <section class="space-y-4">
            <h2 class="text-lg font-bold text-ink">Colour</h2>
            <div class="grid gap-3 sm:grid-cols-4 lg:grid-cols-7">
                @foreach (array_merge([null], $areas) as $area)
                    <div class="overflow-hidden rounded-card border border-line">
                        <div @class(['h-14', \App\Support\Ui::bar($area)])></div>
                        <div @class(['px-3 py-2 text-xs font-semibold', \App\Support\Ui::tint($area)])>{{ $area?->label() ?? 'Brand' }}</div>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-3">
                @foreach (['success', 'warning', 'danger', 'neutral'] as $tone)
                    <x-ui.badge :tone="$tone">{{ ucfirst($tone) }}</x-ui.badge>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-bold text-ink">Buttons</h2>
            @foreach (['primary', 'secondary', 'ghost', 'danger'] as $variant)
                <div class="flex flex-wrap items-center gap-3">
                    @foreach (['sm', 'md', 'lg'] as $size)
                        <x-ui.button :variant="$variant" :size="$size" icon="sparkles">{{ ucfirst($variant) }} {{ $size }}</x-ui.button>
                    @endforeach
                    <x-ui.button :variant="$variant" disabled>Disabled</x-ui.button>
                </div>
            @endforeach
            <div class="flex flex-wrap gap-3">
                @foreach ($areas as $area)
                    <x-ui.button :area="$area">{{ $area->label() }}</x-ui.button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-3">
                <x-ui.button class="pointer-events-none"><svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg> Loading</x-ui.button>
                <x-ui.button href="#" icon-right="arrow-right">As a link</x-ui.button>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Form fields" description="Label, hint, error and optional states.">
                <div class="space-y-4">
                    <x-ui.input name="sg_name" label="Name" hint="This is a hint." />
                    <x-ui.input name="sg_search" icon="search" placeholder="With an icon" aria-label="Search" />
                    <x-ui.input name="sg_optional" label="Optional field" optional />
                    <x-ui.field label="With an error" error="Please fill this in."><input class="block min-h-11 w-full rounded-control border border-danger bg-card px-3 text-sm" aria-invalid="true"></x-ui.field>
                    <x-ui.textarea name="sg_text" label="Textarea" rows="3" />
                    <x-ui.select name="sg_select" label="Select" :options="['a' => 'Option A', 'b' => 'Option B']" placeholder="Choose one" />
                    <x-ui.checkbox name="sg_check" label="Checkbox with a hint" hint="Extra explanation." />
                    <div x-data="{ value: 'weekly' }"><x-ui.segmented label="Segmented control" :options="['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly']" x-model="value" /></div>
                </div>
            </x-ui.card>

            <div class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.stat-card label="Readers" value="1,284" hint="+42 this week" icon="users" area="publish" />
                    <x-ui.stat-card label="Issues left" value="12 of 15" icon="sparkles" area="create" />
                </div>
                <x-ui.card title="Pills and badges">
                    <div class="flex flex-wrap gap-2">
                        <x-ui.pill tone="success">Live</x-ui.pill>
                        <x-ui.pill tone="brand" pulse>Writing</x-ui.pill>
                        <x-ui.pill tone="warning">Scheduled</x-ui.pill>
                        <x-ui.pill tone="danger">Needs a retry</x-ui.pill>
                        <x-ui.pill>Draft</x-ui.pill>
                        <x-ui.badge tone="create" icon="lock-closed">Premium</x-ui.badge>
                    </div>
                </x-ui.card>
                <x-ui.card title="Progress, stepper and skeleton">
                    <div class="space-y-5">
                        <x-ui.progress :value="2" :max="4" label="Getting started" />
                        <x-ui.progress :value="70" area="grow" />
                        <x-ui.stepper :steps="['Pick niche', 'Review', 'Publish', 'Share']" :current="1" />
                        <x-ui.skeleton :lines="3" />
                    </div>
                </x-ui.card>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-bold text-ink">Alerts</h2>
            <x-ui.alert title="Information">Something useful to know.</x-ui.alert>
            <x-ui.alert tone="success">It worked.</x-ui.alert>
            <x-ui.alert tone="warning">Check this before continuing.</x-ui.alert>
            <x-ui.alert tone="danger" title="Something went wrong">Here is how to fix it.</x-ui.alert>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Tabs">
                <x-ui.tabs :tabs="['one' => 'First', 'two' => 'Second', 'three' => 'Third']">
                    <x-ui.tab-panel name="one"><p class="text-sm text-muted">First panel.</p></x-ui.tab-panel>
                    <x-ui.tab-panel name="two"><p class="text-sm text-muted">Second panel.</p></x-ui.tab-panel>
                    <x-ui.tab-panel name="three"><p class="text-sm text-muted">Third panel.</p></x-ui.tab-panel>
                </x-ui.tabs>
            </x-ui.card>
            <x-ui.card title="Overlays and menus">
                <div class="flex flex-wrap gap-3">
                    <x-ui.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'sg-modal')">Open modal</x-ui.button>
                    <x-ui.button variant="secondary" x-data x-on:click="$dispatch('open-slide-over', 'sg-slide')">Open slide-over</x-ui.button>
                    <x-ui.button variant="secondary" x-data x-on:click="$dispatch('toast', { message: 'Saved.', tone: 'success' })">Show toast</x-ui.button>
                    <x-ui.dropdown>
                        <x-slot:trigger><x-ui.button variant="secondary" icon-right="chevron-down">Dropdown</x-ui.button></x-slot:trigger>
                        <x-ui.dropdown-item icon="pencil-square">Edit</x-ui.dropdown-item>
                        <x-ui.dropdown-item icon="share">Share</x-ui.dropdown-item>
                        <x-ui.dropdown-item icon="trash" danger>Delete</x-ui.dropdown-item>
                    </x-ui.dropdown>
                </div>
                <div class="mt-5"><x-ui.copy-field value="https://example.newsletterpub.test" label="Copy field" /></div>
            </x-ui.card>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-bold text-ink">Table and empty state</h2>
            <x-ui.table :headers="['Issue', 'Status', 'Updated']">
                <tr><td class="px-4 py-3 text-ink">How to save on groceries</td><td class="px-4 py-3"><x-ui.pill tone="success">Live</x-ui.pill></td><td class="px-4 py-3 text-muted">2 hours ago</td></tr>
                <tr><td class="px-4 py-3 text-ink">Five budgeting myths</td><td class="px-4 py-3"><x-ui.pill>Draft</x-ui.pill></td><td class="px-4 py-3 text-muted">Yesterday</td></tr>
            </x-ui.table>
            <x-ui.table :headers="['Issue']" :empty="true">
                <x-slot:emptyState>
                    <x-ui.empty-state icon="newspaper" area="create" title="No issues yet" description="Empty states always offer the next action.">
                        <x-ui.button area="create" icon="sparkles">Write my first issue</x-ui.button>
                    </x-ui.empty-state>
                </x-slot:emptyState>
            </x-ui.table>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-bold text-ink">Page header and locked feature</h2>
            <x-ui.page-header title="Page header" description="With an area band and actions." icon="gift" area="grow">
                <x-slot:actions><x-ui.button area="grow">Primary action</x-ui.button></x-slot:actions>
            </x-ui.page-header>
            <x-ui.locked-feature :product="\App\Enums\Product::Oto3Growth" />
            <x-ui.locked-feature :product="\App\Enums\Product::Oto6Social" compact />
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-bold text-ink">Icons</h2>
            <div class="flex flex-wrap gap-3 text-muted">
                @foreach (glob(resource_path('icons/*.svg')) as $icon)
                    @php $name = basename($icon, '.svg'); @endphp
                    <span class="inline-flex flex-col items-center gap-1 rounded-control border border-line p-2 text-[10px]" title="{{ $name }}"><x-ui.icon :name="$name" />{{ $name }}</span>
                @endforeach
            </div>
        </section>
    </div>

    <x-ui.modal name="sg-modal" title="A modal">
        <p class="text-sm text-muted">Modals trap focus and close on Escape.</p>
        <x-slot:footer><x-ui.button variant="secondary" x-on:click="open = false">Close</x-ui.button></x-slot:footer>
    </x-ui.modal>
    <x-ui.slide-over name="sg-slide" title="A slide-over"><p class="text-sm text-muted">Good for forms that need room.</p></x-ui.slide-over>
    <x-ui.toasts />
</body>
</html>
