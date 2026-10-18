<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales come from LaunchpadJV, where one transaction can carry the front end and the order bump,
     * so a ledger row is unique per transaction, product and action.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique(['wp_txn_id', 'action']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->renameColumn('wp_txn_id', 'transaction_id');
            $table->renameColumn('wp_product_id', 'gateway_product_id');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->string('source')->default('launchpadjv')->change();
            $table->unique(['transaction_id', 'product_key', 'action']);
        });

        DB::table('purchases')->where('source', 'warriorplus')->update(['source' => 'launchpadjv']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('purchases')->where('source', 'launchpadjv')->update(['source' => 'warriorplus']);

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique(['transaction_id', 'product_key', 'action']);
            $table->string('source')->default('warriorplus')->change();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->renameColumn('transaction_id', 'wp_txn_id');
            $table->renameColumn('gateway_product_id', 'wp_product_id');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->unique(['wp_txn_id', 'action']);
        });
    }
};
