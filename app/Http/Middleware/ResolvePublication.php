<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Models\Newsletter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finds the publication for a public request from the subdomain, the preview path or a verified custom domain.
 */
class ResolvePublication
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if ($route->hasParameter('customHost')) {
            $host = strtolower((string) $route->parameter('customHost'));
            $newsletter = Domain::query()
                ->where('hostname', $host)
                ->whereNotNull('verified_at')
                ->first()?->newsletter;

            // www of a verified bare domain forwards to the bare domain, which is the primary address.
            if (! $newsletter && str_starts_with($host, 'www.')) {
                $apex = Domain::query()->where('hostname', substr($host, 4))->where('is_apex', true)->whereNotNull('verified_at')->first();

                if ($apex) {
                    return redirect()->away('https://'.$apex->hostname.$request->getRequestUri(), 301);
                }
            }
            $mode = 'custom';
            URL::defaults(['customHost' => $host]);
        } else {
            $subdomain = strtolower((string) $route->parameter('publication'));
            $newsletter = Newsletter::query()->where('subdomain', $subdomain)->first();
            $mode = str_starts_with((string) $route->getName(), 'preview.') ? 'preview' : 'public';
            URL::defaults(['publication' => $subdomain]);
        }

        abort_if(! $newsletter, 404);

        if ($newsletter->isModerated()) {
            return response()->view('public.offline', ['newsletter' => $newsletter], 410);
        }

        $route->forgetParameter('customHost');
        $route->forgetParameter('publication');

        $request->attributes->set('newsletter', $newsletter);
        $request->attributes->set('publication_mode', $mode);

        return $next($request);
    }
}
