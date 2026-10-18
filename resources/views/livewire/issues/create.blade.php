<div class="space-y-6">
    <x-ui.page-header title="Write a new issue" description="Tell the AI what to write about, or let it pick this week's best topic." icon="sparkles" area="create" :back="route('issues.index')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <form wire:submit="generate" class="space-y-5">
                <x-ui.textarea wire:model="topic" label="What should this issue be about?" rows="3" optional
                    hint="Leave it empty and the AI picks the most useful topic in your niche right now." placeholder="For example: how to save on groceries this winter" />

                @if ($ideas->isNotEmpty())
                    <div>
                        <p class="mb-2 text-sm font-medium text-ink">Need an idea?</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($ideas as $idea)
                                <button type="button" wire:key="idea-{{ $loop->index }}" wire:click="useTopic(@js($idea))" class="min-h-11 rounded-full border border-line bg-card px-4 text-left text-sm text-ink hover:border-create hover:bg-create-tint sm:min-h-9">
                                    {{ $idea }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted">
                        @if ($unlimited)
                            Unlimited issues are on.
                        @else
                            {{ $remaining }} of {{ $weeklyLimit }} issues left this week.
                        @endif
                    </p>
                    <x-ui.button type="submit" size="lg" area="create" icon="sparkles" loading="generate" :disabled="! $unlimited && $remaining < 1">Write my issue</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="How it works" class="h-fit">
            <ol class="space-y-4 text-sm">
                <li class="flex gap-3"><x-ui.area-chip area="create" icon="search" size="sm" /><span><strong class="text-ink">Researching.</strong> <span class="text-muted">It looks for fresh stories in your niche.</span></span></li>
                <li class="flex gap-3"><x-ui.area-chip area="create" icon="pencil-square" size="sm" /><span><strong class="text-ink">Writing.</strong> <span class="text-muted">It writes in your newsletter's voice.</span></span></li>
                <li class="flex gap-3"><x-ui.area-chip area="create" icon="swatch" size="sm" /><span><strong class="text-ink">Designing.</strong> <span class="text-muted">It lays out the page in your template.</span></span></li>
            </ol>
            @if (! $unlimited && $remaining < 1)
                <div class="mt-5">
                    <x-ui.locked-feature :product="\App\Enums\Product::Oto2Unlimited" compact />
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
