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
     * @param  string  $email  the email the buyer paid with, stored on the row as buyer_email
     * @param  array<string, mixed>  $payload
     * @param  bool  $sendWelcome  false for the second item of one IPN, so the buyer gets one email
     * @param  User|null  $user  the account to record against, when the caller already knows it (admin grants, refunds of a known sale)
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
        ?User $user = null,
    ): array {
        $email = Str::lower(trim($email));
        $product = $bumpOffer ? null : $product;
        $itemKey = Purchase::itemKey($product, $bumpOffer?->id);
        $find = fn () => Purchase::query()->where('transaction_id', $transactionId)->where('item_key', $itemKey)->where('action', $action)->first();

        if ($existing = $find()) {
            return ['purchase' => $existing, 'duplicate' => true, 'created_user' => false];
        }

        $user ??= self::findBuyer($email);

        if (! $user && $action->revokes()) {
            return ['purchase' => null, 'duplicate' => false, 'created_user' => false];
        }

        // Two IPNs for a new buyer (front end + an upsell) can arrive together; createOrFirst lets the
        // unique email index pick one winner instead of failing the second purchase.
        $defaultPassword = (string) config('newspub.default_password');
        $user ??= User::createOrFirst(['email' => $email], [
            'name' => $name ?: Str::before($email, '@'),
            'password' => Hash::make($defaultPassword !== '' ? $defaultPassword : Str::random(40)),
        ]);
        $createdUser = $user->wasRecentlyCreated;

        if ($createdUser && $defaultPassword !== '') {
            $user->forceFill(['uses_default_password' => true])->save();
        }

        try {
            $purchase = $user->purchases()->create([
                'buyer_email' => $email,
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

        // A purchase that lands on an account with no working login details (for example one made with a
        // reseller code) also gets the welcome email's set-password link, so whoever owns the inbox controls the account.
        if ($sendWelcome && $source === PurchaseSource::LaunchpadJv && ! $action->revokes() && ($createdUser || ! $user->hasLoginDetails())) {
            $user->notify(new WelcomeBuyer);
        }

        return ['purchase' => $purchase, 'duplicate' => false, 'created_user' => $createdUser];
    }

    /**
     * Records an order bump taken on a transaction: the bump line, plus the built-in product it unlocks (if any).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleBump(BumpOffer $bumpOffer, string $email, ?string $name, PurchaseAction $action, string $transactionId, float $amount, ?string $gatewayProductId, array $payload, ?User $user = null): void
    {
        $this->handle(
            email: $email,
            name: $name,
            product: null,
            action: $action,
            transactionId: $transactionId,
            amount: $amount,
            gatewayProductId: $gatewayProductId,
            payload: $payload,
            sendWelcome: false,
            bumpOffer: $bumpOffer,
            user: $user,
        );

        if ($bumpOffer->unlocks_product) {
            $this->handle(
                email: $email,
                name: $name,
                product: $bumpOffer->unlocks_product,
                action: $action,
                transactionId: $transactionId,
                gatewayProductId: $gatewayProductId,
                payload: $payload,
                sendWelcome: false,
                user: $user,
            );
        }
    }

    /**
     * The account that has paid with this email before, even if its login email has since changed;
     * otherwise the account that logs in with it.
     */
    public static function findBuyer(string $email): ?User
    {
        $email = Str::lower(trim($email));
        $userId = Purchase::query()->where('buyer_email', $email)->latest('id')->value('user_id');

        return ($userId ? User::query()->find($userId) : null)
            ?? User::query()->where('email', $email)->first();
    }
}
