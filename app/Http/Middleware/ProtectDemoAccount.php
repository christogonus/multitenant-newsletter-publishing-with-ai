<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo accounts are shared with several people (JV partners, reviewers), so nobody signed in to one may change
 * what the others sign in with or lock them out: the password, the email, two-step sign in and deleting the account.
 * Admins reset a demo account's password from Admin → Buyers.
 */
class ProtectDemoAccount
{
    /**
     * Routes refused for demo accounts. It also runs on Fortify's other routes, which it leaves alone.
     *
     * @var list<string>
     */
    private const GUARDED_ROUTES = ['user-password.update', 'user-profile-information.update', 'two-factor.*', 'account.destroy'];

    public const MESSAGE = 'This is a shared demo account, so its sign-in details cannot be changed.';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_demo || ! $request->routeIs(...self::GUARDED_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, self::MESSAGE);
        }

        return redirect()->route('account')->with('status', 'demo-account-locked');
    }
}
