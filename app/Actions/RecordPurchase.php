<?php

namespace App\Actions;

use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Enums\PurchaseSource;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\WelcomeBuyer;
use App\Support\Entitlements;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Writes one ledger row and refreshes the buyer's features. Idempotent on (transaction ID, action).
 */
class RecordPurchase
{
    public function __construct(private Entitlements $entitlements) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{purchase: ?Purchase, duplicate: bool, created_user: bool}
     */
    public function handle(
        string $email,
        ?string $name,
        Product $product,
        PurchaseAction $action,
        string $transactionId,
        float $amount = 0,
        ?string $warriorPlusProductId = null,
        PurchaseSource $source = PurchaseSource::WarriorPlus,
        array $payload = [],
    ): array {
        $email = Str::lower(trim($email));

        $existing = Purchase::query()->where('wp_txn_id', $transactionId)->where('action', $action)->first();

        if ($existing) {
            return ['purchase' => $existing, 'duplicate' => true, 'created_user' => false];
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user && $action->revokes()) {
            return ['purchase' => null, 'duplicate' => false, 'created_user' => false];
        }

        // Two IPNs for a new buyer (front end + bump) can arrive together; createOrFirst lets the
        // unique email index pick one winner instead of failing the second purchase.
        $user ??= User::createOrFirst(['email' => $email], [
            'name' => $name ?: Str::before($email, '@'),
            'password' => Hash::make(Str::random(40)),
        ]);
        $createdUser = $user->wasRecentlyCreated;

        try {
            $purchase = $user->purchases()->create([
                'source' => $source,
                'wp_txn_id' => $transactionId,
                'wp_product_id' => $warriorPlusProductId,
                'product_key' => $product,
                'amount' => $amount,
                'action' => $action,
                'payload' => $payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Only the (wp_txn_id, action) index can fail here, so this is a genuine redelivery.
            return [
                'purchase' => Purchase::query()->where('wp_txn_id', $transactionId)->where('action', $action)->first(),
                'duplicate' => true,
                'created_user' => false,
            ];
        }

        $this->entitlements->refresh($user);

        // A purchase that lands on an account whose email was never confirmed (for example one made with a
        // reseller code) also gets the set-password link, so whoever owns the inbox controls the account.
        if ($source === PurchaseSource::WarriorPlus && ! $action->revokes() && ($createdUser || $user->email_verified_at === null)) {
            $user->notify(new WelcomeBuyer);
        }

        return ['purchase' => $purchase, 'duplicate' => false, 'created_user' => $createdUser];
    }
}
