<?php

namespace App\Http\Middleware;

use App\Enums\Product;
use Closure;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows the locked-feature screen (never an error) when the buyer does not own the product.
 * A Livewire action is refused outright, because Livewire ignores any response from persistent middleware except a redirect.
 */
class EnsureProduct
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $productKey): Response
    {
        $product = Product::from($productKey);

        if (! $request->user()?->owns($product)) {
            abort_if(Livewire::isLivewireRequest(), 403);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Upgrade to '.$product->label().' to use this.'], 402);
            }

            return response()->view('locked', ['product' => $product], 200);
        }

        return $next($request);
    }
}
