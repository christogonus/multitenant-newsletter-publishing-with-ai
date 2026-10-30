<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated IPs or ranges of the proxies in front of this app, such as
    | a load balancer or Cloudflare. Leave empty when nginx faces the internet.
    | Never use "*": visitors could then fake their IP and dodge rate limits.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
