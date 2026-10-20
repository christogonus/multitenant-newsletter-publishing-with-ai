<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anyone who knows a buyer's email can sign in with the shared starting password. Until the owner confirms
 * their inbox by choosing their own password, the actions that could lock them out are refused:
 * two-step sign in and deleting the account.
 */
class EnsureOwnPassword
{
    /**
     * Fortify routes this guards when it runs as Fortify middleware (it also runs on sign in, which it leaves alone).
     *
     * @var list<string>
     */
    private const GUARDED_ROUTES = ['two-factor.*', 'account.destroy'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->uses_default_password || ! $request->routeIs(...self::GUARDED_ROUTES)) {
            return $next($request);
        }

        $message = 'Choose your own password first. It needs a code from your email, so only you can do it.';

        if ($request->expectsJson()) {
            abort(403, $message);
        }

        return redirect()->route('account')->with('status', 'own-password-required');
    }
}
