<?php

namespace App\Actions;

use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Enums\PurchaseSource;
use App\Models\BumpOffer;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\WelcomeBuyer;
use App\Support\Entitlements;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Writes one ledger row (a product, or an order bump when $bumpOffer is given) and refreshes the buyer's features.
 * Idempotent on (transaction ID, item, action).
 */
class RecordPurchase
{
    public function __construct(private Entitlements $entitlements) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  bool  $sendWelcome  false for the second item of one IPN, so the buyer gets one email
     * @return array{purchase: ?Purchase, duplicate: bool, created_user: bool}
     */
    public function handle(
        string $email,
        ?string $name,
        ?Product $product,
        PurchaseAction $action,
        string $transactionId,
        float $amount = 0,
        ?string $gatewayProductId = null,
        PurchaseSource $source = PurchaseSource::LaunchpadJv,
        array $payload = [],
        bool $sendWelcome = true,
        ?BumpOffer $bumpOffer = null,
    ): array {
        $email = Str::lower(trim($email));
        $product = $bumpOffer ? null : $product;
        $itemKey = Purchase::itemKey($product, $bumpOffer?->id);
        $find = fn () => Purchase::query()->where('transaction_id', $transactionId)->where('item_key', $itemKey)->where('action', $action)->first();

        if ($existing = $find()) {
            return ['purchase' => $existing, 'duplicate' => true, 'created_user' => false];
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user && $action->revokes()) {
            return ['purchase' => null, 'duplicate' => false, 'created_user' => false];
        }

        // Two IPNs for a new buyer (front end + an upsell) can arrive together; createOrFirst lets the
        // unique email index pick one winner instead of failing the second purchase.
        $user ??= User::createOrFirst(['email' => $email], [
            'name' => $name ?: Str::before($email, '@'),
            'password' => Hash::make(Str::random(40)),
        ]);
        $createdUser = $user->wasRecentlyCreated;

        try {
            $purchase = $user->purchases()->create([
                'source' => $source,
                'transaction_id' => $transactionId,
                'gateway_product_id' => $gatewayProductId,
                'product_key' => $product,
                'bump_offer_id' => $bumpOffer?->id,
                'item_key' => $itemKey,
                'amount' => $amount,
                'action' => $action,
                'payload' => $payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Only the (transaction_id, item_key, action) index can fail here, so this is a genuine redelivery.
            return ['purchase' => $find(), 'duplicate' => true, 'created_user' => false];
        }

        $this->entitlements->refresh($user);

        // A purchase that lands on an account whose email was never confirmed (for example one made with a
        // reseller code) also gets the set-password link, so whoever owns the inbox controls the account.
        if ($sendWelcome && $source === PurchaseSource::LaunchpadJv && ! $action->revokes() && ($createdUser || $user->email_verified_at === null)) {
            $user->notify(new WelcomeBuyer);
        }

        return ['purchase' => $purchase, 'duplicate' => false, 'created_user' => $createdUser];
    }
}
