<?php

namespace App\Http\Controllers\Internal;

use App\Actions\RecordPurchase;
use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives LaunchpadJV IPNs for every sale, refund and chargeback. A sale looks like:
 *
 * {
 *   "user_id": 25381, "product_id": 3191, "offer_id": "338",
 *   "transaction_id": "pi_3U9w...", "total_amount": 38.83,
 *   "bump": "true", "action": "SALE", "status": "success",
 *   "checkout_name": "...", "checkout_email": "...",
 *   "user": { "id": 25381, "name": "...", "email": "..." }
 * }
 *
 * - "bump" says the order bump was taken on this same transaction, and "total_amount" includes it.
 * - "action" has been seen as "SALE", "Purchase" and "REFUND", so it is compared case-insensitively.
 * - A refund covers the whole transaction, order bump included.
 */
class LaunchpadIpnController extends Controller
{
    public function __invoke(Request $request, RecordPurchase $recordPurchase): Response
    {
        $expectedKey = (string) config('products.launchpad.webhook_key');

        if ($expectedKey === '') {
            Log::error('IPN rejected: LAUNCHPAD_WEBHOOK_KEY is not set');

            return response('Not configured', 503);
        }

        $providedKey = (string) ($request->query('key') ?? $request->header('X-Webhook-Key', ''));

        if (! hash_equals($expectedKey, $providedKey)) {
            Log::warning('IPN rejected: bad webhook key', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $actionName = strtolower(trim((string) $request->input('action')));
        $action = PurchaseAction::tryFrom((string) (config('products.launchpad.actions')[$actionName] ?? ''));
        $productId = trim((string) $request->input('product_id'));
        $offerId = trim((string) $request->input('offer_id'));
        $product = Product::fromLaunchpad($productId, $offerId);
        $transactionId = trim((string) $request->input('transaction_id'));

        // "user" is the LaunchpadJV account; checkout_* is what was typed at checkout.
        $email = trim((string) ($request->input('user.email') ?: $request->input('checkout_email')));
        $name = trim((string) ($request->input('user.name') ?: $request->input('checkout_name'))) ?: null;

        if (! $action || ! $product) {
            Log::info('IPN ignored: unknown action or product', ['action' => $actionName, 'product_id' => $productId, 'offer_id' => $offerId]);

            return response('Ignored', 200);
        }

        if ($transactionId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response('Missing transaction or buyer email', 422);
        }

        $payload = $request->except('key');

        if ($action->revokes()) {
            return response($this->reverse($recordPurchase, $email, $name, $product, $action, $transactionId, $payload) ? 'OK' : 'Duplicate', 200);
        }

        $total = (float) $request->input('total_amount', 0);
        $tookBump = filter_var($request->input('bump', false), FILTER_VALIDATE_BOOLEAN) && $product !== Product::BumpVault;
        $bumpAmount = $tookBump ? min($total, Product::BumpVault->priceInDollars()) : 0;

        $result = $recordPurchase->handle(
            email: $email,
            name: $name,
            product: $product,
            action: $action,
            transactionId: $transactionId,
            amount: $total - $bumpAmount,
            gatewayProductId: $productId,
            payload: $payload,
        );

        if ($tookBump) {
            $recordPurchase->handle(
                email: $email,
                name: $name,
                product: Product::BumpVault,
                action: $action,
                transactionId: $transactionId,
                amount: $bumpAmount,
                gatewayProductId: $productId,
                payload: $payload,
                sendWelcome: false,
            );
        }

        return response($result['duplicate'] ? 'Duplicate' : 'OK', 200);
    }

    /**
     * Reverses every product bought on the transaction, so refunding the front end also takes back the order bump.
     *
     * @param  array<string, mixed>  $payload
     * @return bool whether anything new was recorded
     */
    private function reverse(RecordPurchase $recordPurchase, string $email, ?string $name, Product $product, PurchaseAction $action, string $transactionId, array $payload): bool
    {
        $sales = Purchase::query()->with('user')->standing()->where('transaction_id', $transactionId)->get();
        $recorded = false;

        if ($sales->isEmpty()) {
            // Nothing standing: either a redelivery of a refund we already have, or a sale we never saw.
            return ! $recordPurchase->handle($email, $name, $product, $action, $transactionId, payload: $payload)['duplicate'];
        }

        foreach ($sales as $sale) {
            $result = $recordPurchase->handle(
                email: $sale->user->email,
                name: $name,
                product: $sale->product_key,
                action: $action,
                transactionId: $transactionId,
                amount: (float) $sale->amount,
                gatewayProductId: $sale->gateway_product_id,
                payload: $payload,
            );

            $recorded = $recorded || ! $result['duplicate'];
        }

        return $recorded;
    }
}
