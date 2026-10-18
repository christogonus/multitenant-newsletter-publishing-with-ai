<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ledger row is now either a product or an order bump. item_key ("oto1_pro" or "bump:12") names which,
     * so a transaction with a bump stays unique per item and action.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('bump_offer_id')->nullable()->after('product_key')->constrained()->restrictOnDelete();
            $table->string('item_key')->default('')->after('bump_offer_id');
            $table->string('product_key')->nullable()->change();
        });

        DB::table('purchases')->update(['item_key' => DB::raw('product_key')]);

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique(['transaction_id', 'product_key', 'action']);
            $table->unique(['transaction_id', 'item_key', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('purchases')->whereNotNull('bump_offer_id')->delete();

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique(['transaction_id', 'item_key', 'action']);
            $table->unique(['transaction_id', 'product_key', 'action']);
            $table->dropConstrainedForeignId('bump_offer_id');
            $table->dropColumn('item_key');
        });
    }
};
