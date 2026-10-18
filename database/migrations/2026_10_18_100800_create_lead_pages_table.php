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
        Schema::create('lead_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('headline');
            $table->string('subheadline')->nullable();
            $table->json('bullets')->nullable();
            $table->string('button_label')->default('Send it to me');
            $table->string('magnet_title');
            $table->json('magnet_blocks')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->boolean('is_live')->default(true);
            $table->timestamps();

            $table->unique(['newsletter_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_pages');
    }
};
