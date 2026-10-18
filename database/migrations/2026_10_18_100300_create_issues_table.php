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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('topic')->nullable();
            $table->string('subject_line')->nullable();
            $table->string('preheader')->nullable();
            $table->json('blocks')->nullable();
            $table->string('status')->default('generating');
            $table->boolean('is_ai_generated')->default(false);
            $table->string('generation_stage')->nullable();
            $table->text('generation_error')->nullable();
            $table->string('public_slug')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['newsletter_id', 'public_slug']);
            $table->index(['status', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
