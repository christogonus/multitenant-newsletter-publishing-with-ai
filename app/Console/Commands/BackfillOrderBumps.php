<?php

namespace App\Console\Commands;

use App\Actions\RecordPurchase;
use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Enums\PurchaseSource;
use App\Models\BumpOffer;
use App\Models\Purchase;
use App\Notifications\UpgradeUnlocked;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Gives the order bump to buyers who ticked it before the product had an active bump, exactly as the IPN
 * would have: the bump line takes its price out of the sale's amount, and the product it unlocks comes with it.
 * Only standing LaunchpadJV sales are looked at, and a transaction that already has a bump line, or a buyer
 * who already owns the bump (say, granted from Admin, Buyers), is left alone, so running it twice is safe.
 */
#[Signature('launchpad:backfill-bumps {--dry-run : Only list the buyers who would get a bump} {--no-email : Do not email buyers that their bump is unlocked}')]
#[Description('Give the order bump to buyers who took it before it was set up')]
class BackfillOrderBumps extends Command
{
    public function handle(RecordPurchase $recordPurchase): int
    {
        $sales = Purchase::query()
            ->standing()
            ->with('user')
            ->where('purchases.source', PurchaseSource::LaunchpadJv)
            ->whereNotNull('purchases.product_key')
            ->where('purchases.product_key', '!=', Product::BumpVault)
            ->whereNotExists(function ($bumps) {
                $bumps->selectRaw('1')
                    ->from('purchases as bumps')
                    ->whereColumn('bumps.transaction_id', 'purchases.transaction_id')
                    ->whereNotNull('bumps.bump_offer_id');
            })
            ->oldest('purchases.id')
            ->get()
            ->filter(fn (Purchase $sale): bool => filter_var($sale->payload['bump'] ?? false, FILTER_VALIDATE_BOOLEAN));

        $granted = 0;
        $alreadyOwned = 0;
        $missingBumps = [];

        foreach ($sales as $sale) {
            $bumpOffer = BumpOffer::activeFor($sale->product_key);
            $label = "{$sale->buyer_email} on {$sale->product_key->label()} (transaction {$sale->transaction_id})";

            if (! $bumpOffer) {
                $missingBumps[$sale->product_key->label()] = ($missingBumps[$sale->product_key->label()] ?? 0) + 1;

                continue;
            }

            if ($bumpOffer->isOwnedBy($sale->user)) {
                $alreadyOwned++;

                continue;
            }

            $granted++;
            $this->line("  {$label}: {$bumpOffer->name}");

            if ($this->option('dry-run')) {
                continue;
            }

            $bumpAmount = min((float) $sale->amount, (float) $bumpOffer->price);
            $sale->update(['amount' => (float) $sale->amount - $bumpAmount]);

            $recordPurchase->handleBump(
                bumpOffer: $bumpOffer,
                email: (string) $sale->buyer_email,
                name: $sale->user->name,
                action: PurchaseAction::Sale,
                transactionId: $sale->transaction_id,
                amount: $bumpAmount,
                gatewayProductId: $sale->gateway_product_id,
                payload: $sale->payload ?? [],
                user: $sale->user,
            );

            if (! $this->option('no-email')) {
                $sale->user->notify(new UpgradeUnlocked($sale->transaction_id));
            }
        }

        $this->components->info($this->option('dry-run')
            ? "{$granted} ".($granted === 1 ? 'buyer' : 'buyers').' would get their order bump.'
            : "Gave {$granted} ".($granted === 1 ? 'buyer' : 'buyers').' their order bump.');

        if ($alreadyOwned > 0) {
            $this->components->twoColumnDetail('Already had it', (string) $alreadyOwned);
        }

        foreach ($missingBumps as $product => $count) {
            $this->components->warn("{$count} ".($count === 1 ? 'buyer' : 'buyers')." took the bump on {$product}, which still has no active order bump. Set one up under Admin, Order bumps, then run this again.");
        }

        return self::SUCCESS;
    }
}
