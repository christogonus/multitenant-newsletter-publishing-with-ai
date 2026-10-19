{{-- Global toast stack. Livewire: $this->dispatch('toast', message: '...', tone: 'success'). Alpine: $dispatch('toast', {message, tone}). --}}
<div
    x-data="{
        toasts: [],
        add(detail) {
            const data = Array.isArray(detail) ? detail[0] : detail;
            const id = Date.now() + Math.random();
            const tone = data.tone ?? 'success';
            this.toasts.push({ id, message: data.message, tone });
            setTimeout(() => this.remove(id), this.solid(tone) ? 9000 : 5000);
        },
        remove(id) { this.toasts = this.toasts.filter(toast => toast.id !== id) },
        {{-- Danger and warning toasts are filled with their colour so they cannot be missed. --}}
        solid(tone) { return tone === 'danger' || tone === 'warning' },
    }"
    x-init="@if (session('toast')) add(@js(session('toast'))) @endif"
    x-on:toast.window="add($event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-card border p-4 shadow-raised"
            :class="{ 'border-line bg-card text-ink': ! solid(toast.tone), 'border-danger bg-danger text-white': toast.tone === 'danger', 'border-warning bg-warning text-white': toast.tone === 'warning' }"
            :role="solid(toast.tone) ? 'alert' : 'status'"
        >
            <template x-if="solid(toast.tone)">
                <x-ui.icon name="warning" size="5" />
            </template>
            <template x-if="! solid(toast.tone)">
                <span
                    class="mt-0.5 size-2.5 shrink-0 rounded-full"
                    :class="{ 'bg-success': toast.tone === 'success', 'bg-brand': toast.tone === 'info' }"
                ></span>
            </template>
            <p class="flex-1 text-sm" :class="solid(toast.tone) ? 'font-semibold' : ''" x-text="toast.message"></p>
            <button type="button" x-on:click="remove(toast.id)" class="-m-2 inline-flex size-9 items-center justify-center rounded-control" :class="solid(toast.tone) ? 'text-white/80 hover:text-white' : 'text-muted hover:text-ink'" aria-label="Dismiss">
                <x-ui.icon name="x-mark" size="4" />
            </button>
        </div>
    </template>
</div>
