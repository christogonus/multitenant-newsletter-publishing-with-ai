@props(['blocks' => []])

{{-- Renders stored blocks. Every value is escaped; URLs were limited to http(s) by App\Support\Blocks. --}}
<div class="content">
    @foreach ($blocks as $block)
        @switch($block['type'] ?? null)
            @case('heading')
                <h2>{{ $block['text'] }}</h2>
                @break
            @case('paragraph')
                <p>{!! nl2br(e($block['text'])) !!}</p>
                @break
            @case('bullets')
                <ul>
                    @foreach ($block['items'] ?? [] as $item)
                        @if (trim($item) !== '')<li>{{ $item }}</li>@endif
                    @endforeach
                </ul>
                @break
            @case('link')
                @if (! empty($block['url']))
                    <a class="link-card" href="{{ $block['url'] }}" target="_blank" rel="noopener nofollow ugc">
                        <strong>{{ $block['text'] ?: $block['url'] }} &rarr;</strong>
                        @if (! empty($block['description']))<span>{{ $block['description'] }}</span>@endif
                    </a>
                @endif
                @break
            @case('image')
                @if (! empty($block['url']))
                    <figure>
                        <img src="{{ $block['url'] }}" alt="{{ $block['alt'] ?? '' }}" loading="lazy">
                        @if (! empty($block['caption']))<figcaption>{{ $block['caption'] }}</figcaption>@endif
                    </figure>
                @endif
                @break
            @case('quote')
                <blockquote>
                    {{ $block['text'] }}
                    @if (! empty($block['cite']))<br><span class="small muted">— {{ $block['cite'] }}</span>@endif
                </blockquote>
                @break
            @case('divider')
                <hr>
                @break
        @endswitch
    @endforeach
</div>
