<x-public.layout :newsletter="$newsletter" :theme="$theme" title="Your referrals">
    <main class="card">
        <h1 style="font-size: 28px;">Your referrals</h1>
        <p>You have brought <strong>{{ $count }}</strong> {{ \Illuminate\Support\Str::plural('friend', $count) }} to {{ $newsletter->name }}.</p>

        @if ($newsletter->reward_tiers)
            <ul class="issue-list">
                @foreach ($newsletter->reward_tiers as $tier)
                    <li>
                        <strong>{{ $tier['reward'] }}</strong>
                        <div class="small muted">
                            @if ($count >= $tier['referrals'])
                                Unlocked. The publisher will send it to you.
                            @else
                                {{ $tier['referrals'] - $count }} more to go
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="section">
            <label class="small muted" for="share-link">Your link</label>
            <input id="share-link" class="field" type="text" readonly value="{{ $shareUrl }}" onfocus="this.select()">
            <x-public.share :url="$shareUrl" :text="'I read '.$newsletter->name.'. You might like it too'" />
        </div>

        <p class="small section"><a href="{{ $unsubscribeUrl }}">Unsubscribe or delete my details</a></p>
    </main>
</x-public.layout>
