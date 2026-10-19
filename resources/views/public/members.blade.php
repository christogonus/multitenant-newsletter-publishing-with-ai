<x-public.layout :newsletter="$newsletter" :theme="$theme" title="Paying readers">
    <main class="card">
        <h1 style="font-size: 28px;">{{ $hasAccess ? 'You are a paying reader' : 'Read every issue of '.$newsletter->name }}</h1>

        @if (session('status'))
            <p role="status"><strong>{{ session('status') }}</strong></p>
        @endif
        @error('payment')<p class="error" role="alert">{{ $message }}</p>@enderror

        @if (! $email)
            @if ($pendingEmail)
                <p>Enter the 6-digit code we emailed to <strong>{{ $pendingEmail }}</strong>.</p>
                <form method="POST" action="{{ \App\Support\PublicLinks::route('members.verify') }}" class="section">
                    @csrf
                    <label for="members-code" class="small muted">Code</label>
                    <input id="members-code" class="field" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required autofocus style="max-width: 220px; letter-spacing: .2em;">
                    @error('code')<p class="error" role="alert">{{ $message }}</p>@enderror
                    <p><button type="submit" class="btn">Continue</button></p>
                </form>
                <form method="POST" action="{{ \App\Support\PublicLinks::route('members.sign-out') }}">
                    @csrf
                    <button type="submit" class="btn secondary">Use a different email</button>
                </form>
            @else
                <p>Enter your email to continue. We will send you a code to confirm it is you, so there is no password to remember.</p>
                <form method="POST" action="{{ \App\Support\PublicLinks::route('members.code') }}" class="section">
                    @csrf
                    <label class="hp" aria-hidden="true">Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    <input type="hidden" name="started_at" value="{{ encrypt(now()->timestamp) }}">
                    <label for="members-email" class="small muted">Email</label>
                    <input id="members-email" class="field" type="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="you@example.com" autocomplete="email">
                    @error('email')<p class="error" role="alert">{{ $message }}</p>@enderror
                    <p><button type="submit" class="btn">Email me a code</button></p>
                </form>
            @endif
        @else
            <p class="small muted">Signed in as {{ $email }}.</p>

            @if ($hasAccess)
                <p>
                    Your access runs until {{ $subscriber->paid_until->toFormattedDateString() }}.
                    @if ($subscriber->paid_via !== \App\Enums\PaymentProvider::Manual)
                        It renews automatically.
                    @endif
                </p>
                <p class="section">
                    @if ($nextUrl)
                        <a class="btn" href="{{ $nextUrl }}">Continue reading</a>
                    @else
                        <a class="btn" href="{{ \App\Support\PublicLinks::route('archive') }}">Read past issues</a>
                    @endif
                </p>
            @else
                @if ($newsletter->paid_perks)
                    <p>{!! nl2br(e($newsletter->paid_perks)) !!}</p>
                @endif

                @php $manual = $newsletter->acceptsManualPayments(); @endphp
                @if ($plans === [] || ($accounts->isEmpty() && ! $manual))
                    <p>Paid subscriptions are not open yet. Please check back soon.</p>
                @else
                    @if ($accounts->isNotEmpty())
                        <div class="section">
                            @foreach ($plans as $option)
                                <div style="margin-bottom: 20px;">
                                    <h2 style="font-size: 20px; margin-bottom: 8px;">{{ $option['price'] }} a {{ $option['plan']->period() }}</h2>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        @foreach ($accounts as $account)
                                            <form method="POST" action="{{ \App\Support\PublicLinks::route('members.checkout') }}">
                                                @csrf
                                                <input type="hidden" name="provider" value="{{ $account->provider->value }}">
                                                <input type="hidden" name="plan" value="{{ $option['plan']->value }}">
                                                <button type="submit" @class(['btn', 'secondary' => ! $loop->first])>
                                                    {{ $account->provider === \App\Enums\PaymentProvider::PayPal ? 'Pay with PayPal' : 'Pay with card' }}
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                            <p class="small muted">Cancel any time. Payments are handled securely by {{ $accounts->map(fn ($account) => $account->provider->label())->join(' and ') }}.</p>
                        </div>
                    @endif

                    @if ($manual)
                        @php
                            $priceList = collect($plans)->map(fn ($option) => $option['price'].' a '.$option['plan']->period())->join(' or ');
                            $mailto = 'mailto:'.$newsletter->paid_manual_contact
                                .'?subject='.rawurlencode('Payment for '.$newsletter->name)
                                .'&body='.rawurlencode("Hi,\n\nI have paid for a subscription to {$newsletter->name}.\n\nMy email: {$email}\nPlan ({$priceList}): \nPayment reference or proof: \n\nThank you");
                        @endphp
                        <div class="section">
                            <h2 style="font-size: 20px; margin-bottom: 8px;">{{ $accounts->isEmpty() ? 'How to pay' : 'Or pay another way' }}</h2>
                            <p><strong>{{ $priceList }}</strong></p>
                            <div style="padding: 16px 20px; border: 1px solid rgba(0,0,0,.15); border-radius: var(--radius); overflow-wrap: anywhere;">{!! nl2br(e($newsletter->paid_manual_instructions)) !!}</div>
                            @if ($pendingClaim)
                                <div role="status" style="margin-top: 16px; padding: 16px 20px; border: 2px solid var(--accent); border-radius: var(--radius);">
                                    <p style="margin: 0 0 6px;"><strong>Waiting for {{ $newsletter->name }} to confirm your payment</strong></p>
                                    <p class="small muted" style="margin: 0;">
                                        {{ $pendingClaim->plan->label() }} plan, sent {{ $pendingClaim->created_at->toFormattedDateString() }}@if ($pendingClaim->reference), reference {{ $pendingClaim->reference }}@endif.
                                        Your access starts once it is confirmed. Come back here and sign in with {{ $email }} to read.
                                    </p>
                                </div>
                            @endif

                            <form method="POST" action="{{ \App\Support\PublicLinks::route('members.paid') }}" style="margin-top: 16px;">
                                @csrf
                                <h3 style="font-size: 17px; margin-bottom: 8px;">{{ $pendingClaim ? 'Update your payment details' : 'Already paid? Let us know' }}</h3>
                                @if (count($plans) > 1)
                                    <fieldset style="border: 0; padding: 0; margin: 0 0 12px;">
                                        <legend class="small muted">Plan you paid for</legend>
                                        @foreach ($plans as $option)
                                            <label style="display: block; padding: 6px 0;">
                                                <input type="radio" name="plan" value="{{ $option['plan']->value }}" @checked(($pendingClaim?->plan ?? $plans[0]['plan']) === $option['plan']) required>
                                                {{ $option['price'] }} a {{ $option['plan']->period() }}
                                            </label>
                                        @endforeach
                                    </fieldset>
                                @else
                                    <input type="hidden" name="plan" value="{{ $plans[0]['plan']->value }}">
                                @endif
                                <label for="members-reference" class="small muted">Payment reference or transaction ID (optional)</label>
                                <input id="members-reference" class="field" type="text" name="reference" maxlength="500" value="{{ old('reference', $pendingClaim?->reference) }}" placeholder="e.g. bank transfer reference or crypto transaction hash">
                                @error('reference')<p class="error" role="alert">{{ $message }}</p>@enderror
                                <p><button type="submit" class="btn">{{ $pendingClaim ? 'Update' : 'I\'ve paid' }}</button></p>
                            </form>

                            <p class="small muted">Questions about your payment? Email <a href="{{ $mailto }}">{{ $newsletter->paid_manual_contact }}</a>.</p>
                        </div>
                    @endif
                @endif
            @endif

            <form method="POST" action="{{ \App\Support\PublicLinks::route('members.sign-out') }}" class="section">
                @csrf
                <button type="submit" class="btn secondary">Sign out</button>
            </form>
        @endif
    </main>
</x-public.layout>
