<x-mail::message>
# Welcome to NewsPub AI, {{ $name }}!

Thank you for your purchase. Your account is ready, and this email has everything you need to publish your first newsletter today.

## Your login details

<x-mail::panel>
**Sign-in page:** [{{ $loginUrl }}]({{ $loginUrl }})<br>
**Email:** {{ $email }}<br>
@if ($defaultPassword)
**Password:** `{{ $defaultPassword }}`
@else
**Password:** choose one with the button below
@endif
</x-mail::panel>

@if ($defaultPassword)
<x-mail::button :url="$loginUrl">
Sign in now
</x-mail::button>

Everyone starts with this password, so choose your own as soon as you sign in: open the account menu at the top right, choose **Account**, and we will email you a code to confirm it is you.
@else
<x-mail::button :url="$setPasswordUrl">
Choose my password
</x-mail::button>

This link works for 7 days. If it stops working, use "Forgot password" on the sign-in page.
@endif

@if ($products !== [])
## What you have unlocked

@foreach ($products as $product)
- **{{ $product['label'] }}**: {{ $product['benefit'] }}
@endforeach
@endif

## Get started in 10 minutes

@foreach ($steps as $step)
**{{ $loop->iteration }}. {{ $step['title'] }}**<br>
{{ $step['body'] }}

@endforeach
## Need help?

Your login details, this guide and answers to common questions are always here, even if this email goes missing: [{{ $accessUrl }}]({{ $accessUrl }})

Still stuck? Reply to {{ $supportEmail }} from the email you bought with. We reply within one working day.

Happy publishing,<br>
The NewsPub AI team
</x-mail::message>
