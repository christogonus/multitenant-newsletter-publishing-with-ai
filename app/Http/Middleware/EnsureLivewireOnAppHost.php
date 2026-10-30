<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the dashboard uses Livewire. On any other host the original page's route does not match,
 * so Livewire would skip the persistent gates (admin, product, suspended) for the action.
 */
class EnsureLivewireOnAppHost
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->routeIs('default-livewire.update') && $request->getHost() !== config('newspub.app_host'), 404);

        return $next($request);
    }
}
