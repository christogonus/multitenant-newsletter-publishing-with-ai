<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('warriorplus');
            $table->string('wp_txn_id');
            $table->string('wp_product_id')->nullable();
            $table->string('product_key')->index();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('action');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['wp_txn_id', 'action']);
            $table->index(['user_id', 'product_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
