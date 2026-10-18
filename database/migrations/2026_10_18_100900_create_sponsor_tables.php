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
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind');
            $table->string('website');
            $table->text('description');
            $table->json('niches')->nullable();
            $table->string('minimum_audience')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('media_kits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_kits');
        Schema::dropIfExists('sponsors');
    }
};
