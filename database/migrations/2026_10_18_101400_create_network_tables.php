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
        Schema::create('network_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('category')->index();
            $table->string('pitch');
            $table->unsignedInteger('audience_size')->default(0);
            $table->boolean('is_seeded')->default(false);
            $table->boolean('is_listed')->default(true);
            $table->timestamps();
        });

        Schema::create('network_swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_listing_id')->constrained('network_listings')->cascadeOnDelete();
            $table->foreignId('recipient_listing_id')->constrained('network_listings')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['requester_listing_id', 'recipient_listing_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_swaps');
        Schema::dropIfExists('network_listings');
    }
};
