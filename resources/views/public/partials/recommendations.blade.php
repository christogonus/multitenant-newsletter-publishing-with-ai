@if (! empty($recommendations) && count($recommendations))
    <section class="section">
        <h2 class="on-bg" style="font-size: 20px;">Newsletters we recommend</h2>
        <ul class="recommend">
            @foreach ($recommendations as $listing)
                <li>
                    <a href="{{ $listing->newsletter->publicUrl() }}" target="_blank" rel="noopener">
                        <strong>{{ $listing->newsletter->name }}</strong>
                        <span class="small muted" style="display: block;">{{ $listing->pitch }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
