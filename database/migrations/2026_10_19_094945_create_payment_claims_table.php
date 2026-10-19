<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Readers saying "I've paid" by a manual method (bank transfer, crypto), waiting for the owner to check and activate them.
     */
    public function up(): void
    {
        Schema::create('payment_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('plan');
            $table->string('reference', 500)->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['newsletter_id', 'status']);
            $table->index(['newsletter_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_claims');
    }
};
