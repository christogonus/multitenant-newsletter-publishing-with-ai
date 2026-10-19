<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureNotSuspended;
use App\Http\Middleware\EnsureProduct;
use App\Http\Middleware\EnsurePublication;
use App\Http\Middleware\ResolvePublication;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/internal.php'));
            Route::middleware('web')->group(base_path('routes/public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Changing a password signs out every other session for that account.
        $middleware->web(append: [AuthenticateSession::class]);

        $middleware->validateCsrfTokens(except: [
            'ipn/*',
            'internal/domains/*/certificate-callback',
            'subscribe',
            'report',
            'join/*',
            'p/*/subscribe',
            'p/*/report',
            'p/*/join/*',
        ]);

        $middleware->alias([
            'product' => EnsureProduct::class,
            'publication' => EnsurePublication::class,
            'publication.public' => ResolvePublication::class,
            'admin' => EnsureAdmin::class,
            'not-suspended' => EnsureNotSuspended::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
