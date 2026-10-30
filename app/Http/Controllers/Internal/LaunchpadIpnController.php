<?php

namespace App\Http\Controllers\Internal;

use App\Actions\RecordPurchase;
use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Http\Controllers\Controller;
use App\Models\BumpOffer;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\IpnNeedsAttention;
use App\Notifications\UpgradeUnlocked;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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
 * - A sale where the buyer is the vendor ("user.id" equals "vendor_id") is LaunchpadJV's test, and is ignored.
 * - Live sales arrive without "transaction_id", "total_amount" or checkout_*: the sale's "id" is the transaction ID then, and the amount is 0.
 * - "bump" says the order bump was taken on this same transaction, and "total_amount" includes it.
 *   It does not say which bump: that is the product's active bump, set up under Admin, Order bumps.
 *   The "Starter + Vault" product already is the bump, so its "bump" flag means nothing more to deliver.
 * - "action" has been seen as "SALE", "Purchase" and "REFUND", so it is compared case-insensitively.
 * - A refund covers the whole transaction, order bump included, and lands on the account that made the sale.
 * - Buyers are matched on the email they paid with before their login email, so changing it on /account loses nothing.
 */
class LaunchpadIpnController extends Controller
{
    public function __invoke(Request $request, RecordPurchase $recordPurchase): Response
    {
        $expectedKey = (string) config('products.launchpad.webhook_key');
        $providedKey = (string) ($request->query('key') ?? $request->header('X-Webhook-Key', ''));
        $hasValidKey = $expectedKey !== '' && hash_equals($expectedKey, $providedKey);

        $this->logReceived($request, $hasValidKey);

        if ($expectedKey === '') {
            Log::error('IPN rejected: LAUNCHPAD_WEBHOOK_KEY is not set');

            return response('Not configured', 503);
        }

        if (! $hasValidKey) {
            Log::warning('IPN rejected: bad webhook key', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $vendorId = trim((string) $request->input('vendor_id'));
        $buyerId = trim((string) ($request->input('user.id') ?: $request->input('user_id')));

        if ($vendorId !== '' && $vendorId === $buyerId) {
            Log::info('IPN ignored: the vendor bought from themselves (a test)', ['vendor_id' => $vendorId, 'product_id' => $request->input('product_id')]);

            return response('Ignored', 200);
        }

        $actionName = strtolower(trim((string) $request->input('action')));
        $action = PurchaseAction::tryFrom((string) (config('products.launchpad.actions')[$actionName] ?? ''));
        $productId = trim((string) $request->input('product_id'));
        $offerId = trim((string) $request->input('offer_id'));
        $product = Product::fromLaunchpad($productId, $offerId);
        $transactionId = trim((string) ($request->input('transaction_id') ?: $request->input('id')));

        // "user" is the LaunchpadJV account; checkout_* is what was typed at checkout.
        $email = trim((string) ($request->input('user.email') ?: $request->input('checkout_email')));
        $name = trim((string) ($request->input('user.name') ?: $request->input('checkout_name'))) ?: null;

        if (! $action || ! $product) {
            Log::info('IPN ignored: unknown action or product', ['action' => $actionName, 'product_id' => $productId, 'offer_id' => $offerId]);

            return response('Ignored', 200);
        }

        if ($transactionId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::error('IPN rejected: missing transaction or buyer email', ['transaction_id' => $transactionId, 'email' => $email, 'product_id' => $productId]);

            return response('Missing transaction or buyer email', 422);
        }

        $payload = $request->except('key');

        if ($action->revokes()) {
            return response($this->reverse($recordPurchase, $email, $name, $product, $action, $transactionId, $payload) ? 'OK' : 'Duplicate', 200);
        }

        $total = (float) $request->input('total_amount', 0);
        $tookBump = $product !== Product::BumpVault && filter_var($request->input('bump', false), FILTER_VALIDATE_BOOLEAN);
        $bumpOffer = $tookBump ? BumpOffer::activeFor($product) : null;
        $bumpAmount = $bumpOffer ? min($total, (float) $bumpOffer->price) : 0;

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

        if ($bumpOffer) {
            $recordPurchase->handleBump($bumpOffer, $email, $name, $action, $transactionId, $bumpAmount, $productId, $payload);
        } elseif ($tookBump && ! $result['duplicate']) {
            // Never fail the sale over it, but make sure someone notices and gives the bump by hand.
            Log::error('IPN: buyer took the order bump but no active bump is set up', ['product' => $product->value, 'transaction_id' => $transactionId]);
            Notification::send(
                User::query()->where('is_admin', true)->get(),
                new IpnNeedsAttention("{$email} took the order bump on {$product->label()} (transaction {$transactionId}), but no active order bump is set up for that product. Set one up under Admin, Order bumps, then give it to this buyer from Admin, Buyers."),
            );
        }

        // New buyers, and accounts still without login details, got the welcome email; everyone else gets one email for everything on the transaction.
        $buyer = $result['purchase']?->user;

        if (! $result['duplicate'] && ! $result['created_user'] && $buyer?->hasLoginDetails()) {
            $buyer->notify(new UpgradeUnlocked($transactionId));
        }

        return response($result['duplicate'] ? 'Duplicate' : 'OK', 200);
    }

    /**
     * Writes every IPN exactly as it arrived to storage/logs/launchpad.log, with the webhook key left out.
     * The body is only kept when the key was right: launchpad:replay trusts what it finds here, and
     * anyone can post a fake sale with a wrong key. A full or unwritable disk never fails the IPN.
     */
    private function logReceived(Request $request, bool $hasValidKey): void
    {
        rescue(fn () => Log::channel('launchpad')->info($hasValidKey ? 'IPN received' : 'IPN refused', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'query' => collect($request->query->all())->except('key')->all(),
            'headers' => collect($request->headers->all())->except(['x-webhook-key', 'cookie', 'authorization'])->map(fn (array $values): string => implode(', ', $values))->all(),
            ...($hasValidKey ? ['body' => $request->getContent()] : []),
        ]));
    }

    /**
     * Reverses every product bought on the transaction, so refunding the front end also takes back the order bump.
     *
     * @param  array<string, mixed>  $payload
     * @return bool whether anything new was recorded
     */
    private function reverse(RecordPurchase $recordPurchase, string $email, ?string $name, Product $product, PurchaseAction $action, string $transactionId, array $payload): bool
    {
        $sales = Purchase::query()->with('user', 'bumpOffer')->standing()->where('transaction_id', $transactionId)->get();
        $recorded = false;

        if ($sales->isEmpty()) {
            // Nothing standing: either a redelivery of a refund we already have, or a sale we never saw.
            return ! $recordPurchase->handle($email, $name, $product, $action, $transactionId, payload: $payload)['duplicate'];
        }

        foreach ($sales as $sale) {
            $result = $recordPurchase->handle(
                email: $email,
                name: $name,
                product: $sale->product_key,
                action: $action,
                transactionId: $transactionId,
                amount: (float) $sale->amount,
                gatewayProductId: $sale->gateway_product_id,
                payload: $payload,
                bumpOffer: $sale->bumpOffer,
                user: $sale->user,
            );

            $recorded = $recorded || ! $result['duplicate'];
        }

        return $recorded;
    }
}
