<x-public.layout :newsletter="$newsletter" :theme="$theme" title="You are in">
    <main class="card">
        <h1 style="font-size: 28px;">You are on the list</h1>
        <p>Thanks for subscribing to {{ $newsletter->name }}. You may get an email asking you to confirm. Click it so the next issue reaches you.</p>

        @if ($subscriber && $referralsEnabled)
            <div class="section">
                <h2 style="font-size: 20px;">Share it with a friend and earn rewards</h2>
                <p class="muted">Every friend who joins with your link counts toward a reward.</p>
                <input class="field" type="text" readonly value="{{ $shareUrl }}" aria-label="Your referral link" onfocus="this.select()">
                <x-public.share :url="$shareUrl" :text="'I read '.$newsletter->name.'. You might like it too'" />
                @if ($newsletter->reward_tiers)
                    <ul style="margin-top: 16px;">
                        @foreach ($newsletter->reward_tiers as $tier)
                            <li><strong>{{ $tier['referrals'] }} {{ \Illuminate\Support\Str::plural('friend', $tier['referrals']) }}:</strong> {{ $tier['reward'] }}</li>
                        @endforeach
                    </ul>
                @endif
                <p class="small"><a href="{{ $statusUrl }}">Check your referral progress</a></p>
            </div>
        @endif

        <p class="section"><a class="btn secondary" href="{{ \App\Support\PublicLinks::route('home') }}">Read past issues</a></p>
    </main>
</x-public.layout>
