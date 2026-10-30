<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Starter + Vault" buyers were stored as not owning the front end. Forgetting the stored
     * answers makes the next check re-read the ledger, which now counts the vault.
     */
    public function up(): void
    {
        DB::table('features')->where('name', 'front_end')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
