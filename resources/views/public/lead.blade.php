<x-public.layout :newsletter="$newsletter" :theme="$theme" :title="$leadPage->headline" :description="$leadPage->subheadline">
    <main class="card">
        <p class="small muted" style="margin: 0 0 8px; text-transform: uppercase; letter-spacing: .06em;">Free: {{ $leadPage->magnet_title }}</p>
        <h1 style="font-size: 32px;">{{ $leadPage->headline }}</h1>
        @if ($leadPage->subheadline)
            <p class="muted" style="font-size: 20px;">{{ $leadPage->subheadline }}</p>
        @endif
        @if ($leadPage->bullets)
            <ul>
                @foreach ($leadPage->bullets as $bullet)
                    <li>{{ $bullet }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ \App\Support\PublicLinks::route('lead.store', ['leadPageSlug' => $leadPage->slug]) }}" class="subscribe">
            <label class="hp" aria-hidden="true">Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            <input type="hidden" name="ref" value="{{ request('ref') }}">
            <input class="field" type="text" name="name" maxlength="120" placeholder="First name (optional)" autocomplete="given-name" aria-label="First name">
            <div>
                <input class="field" type="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="you@example.com" autocomplete="email" aria-label="Your email">
                @error('email')<p class="error" role="alert">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn">{{ $leadPage->button_label }}</button>
        </form>
        <p class="consent">You will also get {{ $newsletter->name }} by email. Unsubscribe any time. <a href="{{ route('legal.privacy') }}">Privacy</a></p>
    </main>
</x-public.layout>
