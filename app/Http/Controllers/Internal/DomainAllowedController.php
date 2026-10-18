<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Newsletter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Answers the proxy's on-demand TLS question (Caddy "ask"): may we issue a certificate for this host?
 * 200 means yes, anything else means no.
 */
class DomainAllowedController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $host = strtolower(trim((string) $request->query('domain')));
        $root = config('newspub.public_root');

        if ($host === '' || strlen($host) > 253) {
            return response('Missing domain', 400);
        }

        if ($host === config('newspub.app_host') || $host === $root) {
            return response('OK', 200);
        }

        if (str_ends_with($host, '.'.$root)) {
            $subdomain = substr($host, 0, -strlen('.'.$root));

            return Newsletter::query()->where('subdomain', $subdomain)->visible()->exists()
                ? response('OK', 200)
                : response('Unknown publication', 404);
        }

        $allowed = Domain::query()
            ->where('hostname', $host)
            ->whereNotNull('verified_at')
            ->whereHas('newsletter', fn ($newsletters) => $newsletters->visible())
            ->exists();

        if ($allowed) {
            Domain::query()->where('hostname', $host)->update(['tls_status' => 'requested']);
        }

        return $allowed ? response('OK', 200) : response('Not allowed', 404);
    }
}
