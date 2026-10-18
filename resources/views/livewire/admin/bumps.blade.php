<div class="space-y-6">
    <x-ui.page-header title="Order bumps" description="What a buyer gets when they tick the bump box on a product's checkout." icon="gift">
        <x-slot:actions>
            <x-ui.button variant="ghost" :href="route('admin.users')">Buyers</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.content')">Content</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>
    @endif

    <x-ui.alert>
        LaunchpadJV only tells us that the buyer took the bump, not which one, so each product has one live bump at a time.
        Switching to a new bump does not affect buyers of the old one: they keep their downloads.
    </x-ui.alert>

    <div class="flex justify-end">
        <x-ui.button wire:click="edit" icon="plus">Add a bump</x-ui.button>
    </div>

    <x-ui.table :headers="['Product', 'Bump', 'Delivers', 'Buyers', 'Status', '']" :empty="$bumps->isEmpty()">
        <x-slot:emptyState><x-ui.empty-state icon="gift" title="No order bumps yet" description="Add one for each product that has a bump on its checkout." /></x-slot:emptyState>
        @foreach ($bumps as $bump)
            <tr wire:key="bump-{{ $bump->id }}">
                <td class="px-4 py-3 text-ink">{{ $bump->product_key->label() }}</td>
                <td class="px-4 py-3">
                    <p class="font-medium text-ink">{{ $bump->name }}</p>
                    <p class="text-xs text-muted">${{ number_format((float) $bump->price, 2) }}</p>
                </td>
                <td class="px-4 py-3 text-muted">
                    {{ $bump->files_count }} {{ \Illuminate\Support\Str::plural('file', $bump->files_count) }}@if ($bump->unlocks_product) + {{ $bump->unlocks_product->label() }}@endif
                    @if ($bump->files_count === 0 && ! $bump->unlocks_product)
                        <x-ui.pill tone="warning">Delivers nothing yet</x-ui.pill>
                    @endif
                </td>
                <td class="px-4 py-3 text-muted tabular-nums">{{ $bump->purchases_count }}</td>
                <td class="px-4 py-3"><x-ui.pill :tone="$bump->is_active ? 'success' : 'neutral'">{{ $bump->is_active ? 'Live' : 'Off' }}</x-ui.pill></td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @if ($bump->is_active)
                        <x-ui.button variant="ghost" size="sm" wire:click="deactivate({{ $bump->id }})">Switch off</x-ui.button>
                    @else
                        <x-ui.button variant="ghost" size="sm" wire:click="activate({{ $bump->id }})" wire:confirm="Make this the live bump on {{ $bump->product_key->label() }}? Any other bump on that product is switched off.">Make live</x-ui.button>
                    @endif
                    <x-ui.button variant="secondary" size="sm" wire:click="edit({{ $bump->id }})">Edit</x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <x-ui.slide-over name="bump-form" :title="$editing ? 'Edit '.$editing->name : 'Add a bump'" x-on:keydown.escape.window="$wire.closeEditor()">
        <div class="space-y-6">
            <form wire:submit="save" id="bump-form" class="space-y-4">
                <x-ui.select wire:model="form.product_key" label="Sold on the checkout of" :options="$products" />
                <x-ui.input wire:model="form.name" label="Name" hint="As buyers saw it on the checkout, for example 100 Instagram Templates." />
                <x-ui.textarea wire:model="form.description" label="Description" rows="2" hint="Shown to buyers on their Downloads page." optional />
                <x-ui.input wire:model="form.price" type="number" step="0.01" min="0" label="Price ($)" hint="Used to split the checkout total between the product and the bump." />
                <x-ui.select wire:model="form.unlocks_product" label="Also unlocks" :options="$unlockable" hint="For a bump that is a built-in feature, such as the Niche and Swipe Vault." />
            </form>

            @if ($editing)
                <div class="space-y-3 border-t border-line pt-5">
                    <p class="text-sm font-semibold text-ink">Files buyers download</p>

                    @if ($editing->files->isEmpty())
                        <p class="text-sm text-muted">No files yet. PDFs, images, or both.</p>
                    @else
                        <ul class="divide-y divide-line rounded-control border border-line">
                            @foreach ($editing->files as $file)
                                <li wire:key="bump-file-{{ $file->id }}" class="flex items-center gap-3 px-3 py-2 text-sm">
                                    @if ($file->isImage())
                                        <img src="{{ route('downloads.file', ['file' => $file, 'inline' => 1]) }}" alt="" class="size-10 shrink-0 rounded object-cover" loading="lazy" />
                                    @else
                                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded bg-subtle text-muted"><x-ui.icon name="document-text" /></span>
                                    @endif
                                    <a href="{{ route('downloads.file', $file) }}" class="min-w-0 flex-1 truncate text-ink hover:underline">{{ $file->name }}</a>
                                    <span class="text-xs text-muted">{{ $file->humanSize() }}</span>
                                    <x-ui.button variant="ghost" size="sm" icon="trash" wire:click="deleteFile({{ $file->id }})" wire:confirm="Remove {{ $file->name }}? Buyers will no longer see it." aria-label="Remove {{ $file->name }}" />
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ route('admin.bumps.files.store', $editing) }}" enctype="multipart/form-data" class="space-y-2">
                        @csrf
                        <label for="bump-files" class="block text-sm font-medium text-ink">Add files</label>
                        <input id="bump-files" type="file" name="files[]" multiple accept=".pdf,.png,.jpg,.jpeg,.webp,.gif,.zip" required
                            class="block w-full text-sm text-muted file:mr-3 file:min-h-11 file:rounded-control file:border-0 file:bg-subtle file:px-4 file:font-semibold file:text-ink" />
                        <p class="text-xs text-muted">PDF, PNG, JPG, WebP, GIF or zip. Up to 50 files at once, {{ $maxFileMegabytes }} MB each.</p>
                        @foreach ($uploadErrors as $message)
                            <p class="text-sm text-danger" role="alert">{{ $message }}</p>
                        @endforeach
                        <x-ui.button type="submit" variant="secondary" icon="plus">Upload</x-ui.button>
                    </form>
                </div>

                <div class="border-t border-line pt-5">
                    <x-ui.button variant="ghost" icon="trash" wire:click="delete({{ $editing->id }})" wire:confirm="Delete this bump? If anyone has bought it, it is switched off instead so they keep their downloads.">Delete bump</x-ui.button>
                </div>
            @endif
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false; $wire.closeEditor()">Close</x-ui.button>
            <x-ui.button type="submit" form="bump-form" loading="save">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.slide-over>
</div>
