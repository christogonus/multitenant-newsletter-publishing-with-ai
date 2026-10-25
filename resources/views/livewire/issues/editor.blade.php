<div class="space-y-6">
    @if ($issue->status === \App\Enums\IssueStatus::Generating)
        <div wire:poll.2s="refreshGeneration">
            <x-ui.page-header :title="$issue->topic ?: 'Your new newsletter'" description="Hang tight. This usually takes under a minute." icon="sparkles" area="create" :back="route('issues.index')" />

            @php
                $stages = [\App\Enums\GenerationStage::Researching, \App\Enums\GenerationStage::Writing, \App\Enums\GenerationStage::Designing];
                $current = $issue->generation_stage?->position() ?? 0;
            @endphp
            <x-ui.card class="mt-6">
                <ol class="grid gap-4 sm:grid-cols-3" aria-live="polite">
                    @foreach ($stages as $stage)
                        @php $state = $stage->position() < $current ? 'done' : ($stage->position() === $current ? 'active' : 'waiting'); @endphp
                        <li @class([
                            'rounded-card border p-5 transition',
                            'border-create bg-create-tint' => $state === 'active',
                            'border-line bg-card' => $state !== 'active',
                        ])>
                            <div class="flex items-center gap-3">
                                <span @class([
                                    'flex size-9 items-center justify-center rounded-full',
                                    'bg-success text-white' => $state === 'done',
                                    'bg-create text-white' => $state === 'active',
                                    'bg-subtle text-muted' => $state === 'waiting',
                                ])>
                                    @if ($state === 'done')
                                        <x-ui.icon name="check" size="4" />
                                    @elseif ($state === 'active')
                                        <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                                    @else
                                        {{ $stage->position() + 1 }}
                                    @endif
                                </span>
                                <span class="text-sm font-semibold text-ink">{{ $stage->label() }}</span>
                            </div>
                            @if ($state === 'active')
                                <x-ui.skeleton :lines="2" class="mt-4" />
                            @endif
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>
    @elseif ($issue->status === \App\Enums\IssueStatus::Failed)
        <x-ui.page-header :title="$issue->title" icon="sparkles" area="create" :back="route('issues.index')" />
        <x-ui.alert tone="danger" title="This newsletter did not finish">
            {{ $issue->generation_error ?: 'Something went wrong while writing.' }} It did not count toward your weekly newsletters.
            <x-slot:actions>
                <x-ui.button wire:click="retry" icon="retry" loading="retry" area="create">Try again</x-ui.button>
            </x-slot:actions>
        </x-ui.alert>
    @else
        <x-ui.page-header :title="$title ?: 'Untitled newsletter'" icon="pencil-square" area="create" :back="route('issues.index')">
            <x-slot:actions>
                <x-ui.pill :tone="$issue->status->tone()">{{ $issue->status->label() }}</x-ui.pill>
                <x-ui.dropdown>
                    <x-slot:trigger>
                        <x-ui.button variant="secondary" icon-right="chevron-down">More</x-ui.button>
                    </x-slot:trigger>
                    @if ($issue->isPublished())
                        <x-ui.dropdown-item :href="$issue->publicUrl()" icon="eye" target="_blank">View live page</x-ui.dropdown-item>
                        <x-ui.dropdown-item :href="route('issues.share', $issue)" icon="share">Share</x-ui.dropdown-item>
                    @endif
                    @if ($canSchedule)
                        <x-ui.dropdown-item x-on:click="$dispatch('open-modal', 'schedule')" icon="calendar">Schedule</x-ui.dropdown-item>
                    @endif
                    @if ($canExport)
                        <x-ui.dropdown-item :href="route('issues.export', $issue)" icon="download">Download email file</x-ui.dropdown-item>
                    @endif
                    @if ($canRepurpose)
                        <x-ui.dropdown-item :href="route('social.index', ['issue' => $issue->id])" icon="share">Turn into social posts</x-ui.dropdown-item>
                    @endif
                    <x-ui.dropdown-item wire:click="duplicate" icon="clipboard">Duplicate</x-ui.dropdown-item>
                    @if ($issue->isPublished())
                        <x-ui.dropdown-item wire:click="unpublish" icon="x-mark">Hide from my page</x-ui.dropdown-item>
                    @endif
                    <x-ui.dropdown-item x-on:click="$dispatch('open-modal', 'delete-issue')" icon="trash" danger>Delete</x-ui.dropdown-item>
                </x-ui.dropdown>
                <x-ui.button wire:click="publish" size="lg" area="create" icon="globe" loading="publish">{{ $issue->isPublished() ? 'Update live page' : 'Publish' }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <div x-data="{ tab: 'edit' }">
        <div role="tablist" class="mb-4 flex gap-1 rounded-control bg-subtle p-1 lg:hidden">
            @foreach (['edit' => 'Edit', 'preview' => 'Preview'] as $key => $label)
                <button type="button" role="tab" x-on:click="tab = '{{ $key }}'" x-bind:aria-selected="(tab === '{{ $key }}').toString()"
                    x-bind:class="tab === '{{ $key }}' ? 'bg-card text-ink shadow-card' : 'text-muted'"
                    class="min-h-11 flex-1 rounded-[6px] text-sm font-semibold">{{ $label }}</button>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-4" x-bind:class="tab === 'edit' ? '' : 'hidden lg:block'">
                <x-ui.card title="Email details">
                    <div class="space-y-4">
                        <x-ui.input wire:model.blur="title" label="Title" />
                        <x-ui.input wire:model.blur="subjectLine" label="Email subject line" hint="What readers see in their inbox. Keep it under 60 characters." />
                        <x-ui.input wire:model.blur="preheader" label="Preview text" hint="The short line shown after the subject." optional />
                        @if ($canLock)
                            <div>
                                <x-ui.checkbox wire:model.live="paidOnly" label="Paying readers only" hint="Everyone sees the title and the opening up to the first paragraph. Paying readers sign in with their email to read the rest." />
                                @if ($paidOnly && ! $this->newsletter->hasPaidSubscriptions())
                                    <x-ui.alert tone="warning" class="mt-2">This newsletter stays free until you turn on paid subscriptions. <a href="{{ route('sponsors.index', ['tab' => 'paid']) }}" class="font-semibold underline">Set them up</a></x-ui.alert>
                                @endif
                            </div>
                        @endif
                    </div>
                </x-ui.card>

                <x-ui.card title="Content" description="Drag the handle to reorder. Changes save automatically.">
                    <ul wire:sort="moveBlock" class="space-y-3">
                        @foreach ($blocks as $index => $block)
                            <li wire:key="block-{{ $block['id'] }}" wire:sort:item="{{ $block['id'] }}" class="rounded-card border border-line bg-card">
                                <div class="flex items-center gap-2 border-b border-line px-3 py-1.5">
                                    <button type="button" wire:sort:handle class="inline-flex size-11 cursor-grab items-center justify-center rounded-control text-muted hover:bg-subtle sm:size-9" aria-label="Drag to reorder">
                                        <x-ui.icon name="grip" size="5" />
                                    </button>
                                    <span class="flex-1 text-xs font-semibold tracking-wide text-muted uppercase">{{ \App\Enums\BlockType::from($block['type'])->label() }}</span>
                                    <button type="button" wire:click="removeBlock('{{ $block['id'] }}')" class="inline-flex size-11 items-center justify-center rounded-control text-muted hover:bg-danger-tint hover:text-danger sm:size-9" aria-label="Remove block">
                                        <x-ui.icon name="trash" size="4" />
                                    </button>
                                </div>
                                <div class="space-y-3 p-3">
                                    @switch($block['type'])
                                        @case('heading')
                                            <x-ui.input wire:model.blur="blocks.{{ $index }}.text" aria-label="Heading" class="font-semibold" />
                                            @break
                                        @case('paragraph')
                                            <x-ui.textarea wire:model.blur="blocks.{{ $index }}.text" rows="4" aria-label="Text" />
                                            @break
                                        @case('quote')
                                            <x-ui.textarea wire:model.blur="blocks.{{ $index }}.text" rows="2" aria-label="Quote" />
                                            <x-ui.input wire:model.blur="blocks.{{ $index }}.cite" placeholder="Who said it (optional)" aria-label="Quote source" />
                                            @break
                                        @case('bullets')
                                            @foreach ($block['items'] ?? [] as $itemIndex => $item)
                                                <div class="flex items-center gap-2" wire:key="block-{{ $block['id'] }}-item-{{ $itemIndex }}">
                                                    <span class="text-muted" aria-hidden="true">•</span>
                                                    <x-ui.input wire:model.blur="blocks.{{ $index }}.items.{{ $itemIndex }}" aria-label="List item" class="flex-1" />
                                                    <button type="button" wire:click="removeBullet({{ $index }}, {{ $itemIndex }})" class="inline-flex size-11 shrink-0 items-center justify-center rounded-control text-muted hover:text-danger" aria-label="Remove item"><x-ui.icon name="x-mark" size="4" /></button>
                                                </div>
                                            @endforeach
                                            <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="addBullet({{ $index }})">Add item</x-ui.button>
                                            @break
                                        @case('link')
                                            <x-ui.input wire:model.blur="blocks.{{ $index }}.text" placeholder="Link text" aria-label="Link text" />
                                            <x-ui.input wire:model.blur="blocks.{{ $index }}.url" type="url" placeholder="https://" aria-label="Web address" />
                                            <x-ui.input wire:model.blur="blocks.{{ $index }}.description" placeholder="Short description (optional)" aria-label="Link description" />
                                            @break
                                        @case('image')
                                            @if (! empty($block['url']))
                                                <img src="{{ $block['url'] }}" alt="" class="max-h-40 rounded-control border border-line">
                                            @endif
                                            <div>
                                                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-control border border-line bg-card px-4 text-sm font-semibold text-ink hover:bg-subtle">
                                                    <x-ui.icon name="upload" size="4" />
                                                    <span wire:loading.remove wire:target="imageUploads.{{ $block['id'] }}">{{ empty($block['url']) ? 'Upload a picture' : 'Replace picture' }}</span>
                                                    <span wire:loading wire:target="imageUploads.{{ $block['id'] }}">Uploading…</span>
                                                    <input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp,image/gif" wire:model="imageUploads.{{ $block['id'] }}">
                                                </label>
                                                @error('imageUploads.'.$block['id'])<p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                                            </div>
                                            <x-ui.input wire:model.blur="blocks.{{ $index }}.url" type="url" placeholder="Or paste a picture web address, starting with https://" aria-label="Picture address" />
                                            <x-ui.input wire:model.blur="blocks.{{ $index }}.alt" placeholder="Describe the picture for screen readers" aria-label="Picture description" />
                                            <x-ui.input wire:model.blur="blocks.{{ $index }}.caption" placeholder="Caption (optional)" aria-label="Caption" />
                                            @break
                                        @case('divider')
                                            <hr class="border-line">
                                            @break
                                    @endswitch
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    @if ($blocks === [])
                        <x-ui.empty-state icon="document-text" area="create" title="This newsletter is empty" description="Add a heading or some text to get started." />
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($blockTypes as $type)
                            <x-ui.button variant="secondary" size="sm" icon="plus" wire:click="addBlock('{{ $type->value }}')">{{ $type->label() }}</x-ui.button>
                        @endforeach
                    </div>
                </x-ui.card>
            </div>

            <div x-bind:class="tab === 'preview' ? '' : 'hidden lg:block'">
                <div class="sticky top-20">
                    <x-ui.card title="Preview" :padding="false">
                        <x-slot:actions>
                            <span class="text-xs text-muted" wire:loading.remove>Up to date</span>
                            <span class="text-xs text-muted" wire:loading>Saving…</span>
                        </x-slot:actions>
                        <iframe
                            wire:key="preview-{{ $previewVersion }}"
                            src="{{ route('issues.preview', $issue) }}?v={{ $previewVersion }}"
                            title="Newsletter preview"
                            class="h-[70vh] w-full rounded-b-card bg-white"
                            loading="lazy"
                        ></iframe>
                    </x-ui.card>
                </div>
            </div>
        </div>
        </div>

        <x-ui.modal name="schedule" title="Schedule this newsletter">
            <p class="mb-4 text-sm text-muted">It goes live on your page at this time, and to your connected email services if auto-send is on.</p>
            <x-ui.input type="datetime-local" wire:model="scheduleAt" label="Date and time" />
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                <x-ui.button wire:click="schedule" area="automate" icon="calendar" loading="schedule">Schedule</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="delete-issue" title="Delete this newsletter?" max-width="md">
            <p class="text-sm text-muted">It is removed from your page and cannot be brought back.</p>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Keep it</x-ui.button>
                <x-ui.button variant="danger" wire:click="delete" loading="delete">Delete newsletter</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
