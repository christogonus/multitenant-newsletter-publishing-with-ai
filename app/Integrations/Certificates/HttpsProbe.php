<?php

namespace App\Integrations\Certificates;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The ground truth for "does the padlock work?": connect to https://{hostname} with certificate
 * verification on. Any HTTP answer means the certificate is valid for that name.
 */
class HttpsProbe
{
    public function isSecure(string $hostname): bool
    {
        try {
            Http::timeout(10)
                ->connectTimeout(8)
                ->withOptions(['allow_redirects' => false, 'verify' => true])
                ->withUserAgent('NewsPubAI-CertificateCheck/1.0')
                ->get('https://'.$hostname.'/');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
