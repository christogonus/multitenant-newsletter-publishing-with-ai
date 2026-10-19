<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual payments (OTO 4): readers pay by bank transfer, crypto or similar, then email the owner to be let in.
     */
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->boolean('paid_manual_enabled')->default(false);
            $table->text('paid_manual_instructions')->nullable();
            $table->string('paid_manual_contact')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->dropColumn(['paid_manual_enabled', 'paid_manual_instructions', 'paid_manual_contact']);
        });
    }
};
