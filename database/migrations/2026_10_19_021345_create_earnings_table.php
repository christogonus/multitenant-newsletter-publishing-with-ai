<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money a newsletter brought in, logged by its owner (OTO 9 portfolio dashboard).
     */
    public function up(): void
    {
        Schema::create('earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('earned_on');
            $table->timestamps();

            $table->index(['newsletter_id', 'earned_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('earnings');
    }
};
