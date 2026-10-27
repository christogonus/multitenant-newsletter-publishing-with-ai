@props(['withButton' => true])

{{-- Reminds admins to write this month's Trend Radar edition and email it to buyers, until that email is sent. --}}
@php
    $trendRadar = app(\App\Support\TrendRadar::class);
    $edition = $trendRadar->dueEdition();
@endphp

@if ($edition)
    <x-ui.alert tone="warning" title="Trend Radar Edition {{ $edition }} ({{ $trendRadar->month($edition) }}) is due">
        <ol class="mt-1 list-decimal space-y-1 pl-5">
            <li>Write Edition {{ $edition }}: copy the Edition 1 file in <code>storage/app/private/bonuses/src</code>, update it with this month's trends (see <code>trend-radar-editor-guide.md</code>), then run <code>build.sh</code>.</li>
            <li>Add the new PDF to your bonus delivery page and copy its link.</li>
            <li>Email it to buyers. The email is ready to edit; paste the link into the button.</li>
        </ol>
        @if ($withButton)
            <x-ui.button size="sm" class="mt-3" icon="envelope" :href="route('admin.broadcasts', ['sample' => 'trend_radar'])">Write the email</x-ui.button>
        @endif
    </x-ui.alert>
@endif
