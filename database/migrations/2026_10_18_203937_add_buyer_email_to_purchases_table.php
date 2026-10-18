<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The email the buyer paid with, kept on every ledger row so later IPNs still find the account after
     * the buyer changes their login email. Existing rows take the account's current email.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('buyer_email')->nullable()->after('user_id')->index();
        });

        DB::table('purchases')->update([
            'buyer_email' => DB::raw('(select users.email from users where users.id = purchases.user_id)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['buyer_email']);
            $table->dropColumn('buyer_email');
        });
    }
};
