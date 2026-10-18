@props(['newsletter', 'action' => null, 'button' => 'Subscribe', 'inline' => true, 'referralCode' => null, 'heading' => null])

<div class="section">
    @if ($heading)
        <h3>{{ $heading }}</h3>
    @endif
    <form method="POST" action="{{ $action ?? \App\Support\PublicLinks::route('subscribe') }}" @class(['subscribe', 'inline' => $inline])>
        <label class="hp" aria-hidden="true">Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        <input type="hidden" name="ref" value="{{ $referralCode ?? request('ref') }}">
        <input type="hidden" name="started_at" value="{{ encrypt(now()->timestamp) }}">
        <div>
            <label for="subscribe-email" class="hp">Email</label>
            <input id="subscribe-email" class="field" type="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="you@example.com" autocomplete="email" aria-label="Your email">
            @error('email')<p class="error" role="alert">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn">{{ $button }}</button>
    </form>
    <p class="consent">
        By subscribing you agree to receive emails from {{ $newsletter->name }}. Unsubscribe any time.
        <a href="{{ route('legal.privacy') }}">Privacy</a>
    </p>
</div>
