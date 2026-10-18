<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Order bumps are set up by an admin per product: downloadable files, a built-in product to unlock, or both.
     */
    public function up(): void
    {
        Schema::create('bump_offers', function (Blueprint $table) {
            $table->id();
            $table->string('product_key')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('unlocks_product')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('bump_offer_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bump_offer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bump_offer_files');
        Schema::dropIfExists('bump_offers');
    }
};
