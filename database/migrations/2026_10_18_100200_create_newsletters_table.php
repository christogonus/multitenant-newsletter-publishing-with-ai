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
        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->string('niche');
            $table->string('audience')->nullable();
            $table->string('frequency');
            $table->string('subdomain')->unique();
            $table->string('voice')->nullable();
            $table->string('template')->default('classic');
            $table->json('branding')->nullable();
            $table->json('reward_tiers')->nullable();
            $table->boolean('auto_publish')->default(false);
            $table->string('kit_key')->nullable();
            $table->json('topic_pipeline')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->string('moderation_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletters');
    }
};
