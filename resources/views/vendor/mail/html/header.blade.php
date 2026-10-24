@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('icon-mail.png') }}" class="brand-mark" width="32" height="32" alt="">{{ trim($slot) }}
</a>
</td>
</tr>
