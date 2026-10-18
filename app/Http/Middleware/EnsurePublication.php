<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends buyers without a publication to the start wizard.
 */
class EnsurePublication
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->newsletters()->exists()) {
            return redirect()->route('newsletters.create')
                ->with('toast', ['message' => 'Let’s set up your newsletter first. It takes about a minute.', 'tone' => 'info']);
        }

        return $next($request);
    }
}
