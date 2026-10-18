<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Several publications may claim a hostname while unverified; only a verified claim reserves it.
 * This stops buyers squatting someone else's domain before the real owner can verify it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropUnique(['hostname']);
            $table->index(['hostname', 'verified_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex(['hostname', 'verified_at']);
            $table->unique('hostname');
        });
    }
};
