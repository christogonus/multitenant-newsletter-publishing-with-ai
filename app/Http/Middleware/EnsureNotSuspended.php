<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended owner sees only the "account on hold" page in place of the dashboard.
 * A Livewire action is refused outright, because Livewire ignores any response from persistent middleware except a redirect.
 */
class EnsureNotSuspended
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuspended()) {
            abort_if(Livewire::isLivewireRequest(), 403);

            return response()->view('account.suspended', [], 403);
        }

        return $next($request);
    }
}
