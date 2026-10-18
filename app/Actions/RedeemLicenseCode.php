<?php

namespace App\Actions;

use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Enums\PurchaseSource;
use App\Models\LicenseCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Turns a reseller's code (OTO 12) into an account with a front-end ledger row. Each code works once.
 */
class RedeemLicenseCode
{
    public function __construct(private RecordPurchase $recordPurchase) {}

    /**
     * @throws ValidationException
     */
    public function handle(string $code, string $name, string $email, string $password, ?User $reseller = null): User
    {
        $code = self::normalize($code);

        return DB::transaction(function () use ($code, $name, $email, $password, $reseller) {
            $licenseCode = LicenseCode::query()
                ->where('code', $code)
                ->when($reseller, fn ($query) => $query->where('reseller_id', $reseller->id))
                ->lockForUpdate()
                ->first();

            // Codes stop working once the reseller's own licence is refunded or charged back.
            if (! $licenseCode || ! $licenseCode->isAvailable() || ! $licenseCode->reseller?->owns(Product::Oto12Reseller)) {
                throw ValidationException::withMessages(['code' => 'That code is not valid or has already been used.']);
            }

            if (User::query()->where('email', strtolower($email))->exists()) {
                throw ValidationException::withMessages(['email' => 'This email already has an account. Use a different email, or contact support to add this code to your account.']);
            }

            $user = User::create([
                'name' => $name,
                'email' => strtolower($email),
                'password' => Hash::make($password),
                'terms_accepted_at' => now(),
            ]);

            $this->recordPurchase->handle(
                email: $user->email,
                name: $user->name,
                product: $licenseCode->product_key,
                action: PurchaseAction::Sale,
                transactionId: self::transactionId($licenseCode),
                source: PurchaseSource::LicenseCode,
                payload: ['license_code_id' => $licenseCode->id, 'reseller_id' => $licenseCode->reseller_id],
                user: $user,
            );

            $licenseCode->update(['redeemed_by' => $user->id, 'redeemed_at' => now()]);

            return $user;
        });
    }

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', trim($code)));
    }

    public static function transactionId(LicenseCode $licenseCode): string
    {
        return 'LC-'.$licenseCode->code;
    }
}
