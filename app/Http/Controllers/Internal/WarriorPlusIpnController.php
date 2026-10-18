<?php

namespace App\Http\Controllers\Internal;

use App\Actions\RecordPurchase;
use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives WarriorPlus IPNs for every sale, refund and chargeback.
 */
class WarriorPlusIpnController extends Controller
{
    public function __invoke(Request $request, RecordPurchase $recordPurchase): Response
    {
        $fields = config('products.warriorplus.fields');
        $expectedKey = (string) config('products.warriorplus.security_key');

        if ($expectedKey === '' || ! hash_equals($expectedKey, (string) $request->input($fields['security_key']))) {
            Log::warning('IPN rejected: bad security key', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $actionName = strtolower((string) $request->input($fields['action']));
        $action = PurchaseAction::tryFrom((string) (config('products.warriorplus.actions')[$actionName] ?? ''));
        $product = Product::fromWarriorPlusProductId((string) $request->input($fields['product_id']));
        $transactionId = (string) ($request->input($fields['transaction_id']) ?: $request->input($fields['sale_id']));
        $email = (string) $request->input($fields['buyer_email']);

        if (! $action || ! $product) {
            Log::info('IPN ignored: unknown action or product', [
                'action' => $actionName,
                'product_id' => $request->input($fields['product_id']),
            ]);

            return response('Ignored', 200);
        }

        if ($transactionId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response('Missing transaction or buyer email', 422);
        }

        $result = $recordPurchase->handle(
            email: $email,
            name: $request->input($fields['buyer_name']),
            product: $product,
            action: $action,
            transactionId: $transactionId,
            amount: (float) $request->input($fields['amount'], 0),
            warriorPlusProductId: (string) $request->input($fields['product_id']),
            payload: $request->except($fields['security_key']),
        );

        return response($result['duplicate'] ? 'Duplicate' : 'OK', 200);
    }
}
