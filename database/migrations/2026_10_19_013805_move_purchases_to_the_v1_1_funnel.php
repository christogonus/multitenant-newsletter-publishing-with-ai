<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Funnel v1.1: OTO 1 Lite and Pro become one Delivery product, and OTO 2 Unlimited becomes Gold
     * (it already had unlimited issues and the faster queue).
     */
    public function up(): void
    {
        // A transaction holding both Lite and Pro would collide once both are renamed; Pro already covers Lite.
        // Selected first and deleted by id, because MySQL cannot delete from a table its own subquery reads.
        $coveredLiteIds = DB::table('purchases')
            ->where('product_key', 'oto1_lite')
            ->whereExists(fn ($pro) => $pro->selectRaw('1')
                ->from('purchases as pro')
                ->whereColumn('pro.transaction_id', 'purchases.transaction_id')
                ->whereColumn('pro.action', 'purchases.action')
                ->where('pro.product_key', 'oto1_pro'))
            ->pluck('id');

        DB::table('purchases')->whereIn('id', $coveredLiteIds)->delete();

        $this->rename(['oto1_lite', 'oto1_pro'], 'oto1_delivery');
        $this->rename(['oto2_unlimited'], 'oto2_gold');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rename(['oto1_delivery'], 'oto1_pro');
        $this->rename(['oto2_silver', 'oto2_gold'], 'oto2_unlimited');
    }

    /**
     * @param  list<string>  $from
     */
    private function rename(array $from, string $to): void
    {
        DB::table('purchases')->whereIn('product_key', $from)->update(['product_key' => $to, 'item_key' => $to]);
        DB::table('bump_offers')->whereIn('product_key', $from)->update(['product_key' => $to]);

        // Stored Pennant values are re-resolved from the ledger on the next check.
        DB::table('features')->whereIn('name', [...$from, $to])->delete();
    }
};
