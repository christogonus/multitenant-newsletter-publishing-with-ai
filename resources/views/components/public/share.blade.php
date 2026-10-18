@props(['url', 'text'])

@php
    $encodedUrl = urlencode($url);
    $encodedText = urlencode($text);
@endphp

<div class="share">
    <a href="https://twitter.com/intent/tweet?text={{ $encodedText }}&url={{ $encodedUrl }}" target="_blank" rel="noopener">Share on X</a>
    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $encodedUrl }}" target="_blank" rel="noopener">LinkedIn</a>
    <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedUrl }}" target="_blank" rel="noopener">Facebook</a>
    <a href="https://wa.me/?text={{ $encodedText }}%20{{ $encodedUrl }}" target="_blank" rel="noopener">WhatsApp</a>
    <a href="mailto:?subject={{ rawurlencode($text) }}&body={{ rawurlencode($url) }}">Email</a>
</div>
