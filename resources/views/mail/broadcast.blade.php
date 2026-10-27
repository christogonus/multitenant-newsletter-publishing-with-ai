<x-mail::message>
{{ $body }}

@if ($buttonLabel && $buttonUrl)
<x-mail::button :url="$buttonUrl">
{{ $buttonLabel }}
</x-mail::button>
@endif

<x-mail::subcopy>
You are getting this because you have a {{ config('app.name') }} account. [Unsubscribe from these emails]({{ $unsubscribeUrl }}). Emails about your account and purchases still arrive.
</x-mail::subcopy>
</x-mail::message>
