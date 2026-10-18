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
        Schema::create('platform_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->text('credentials')->nullable();
            $table->string('status')->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('last_pushed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['newsletter_id', 'platform']);
        });

        Schema::create('issue_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_connection_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('remote_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['issue_id', 'platform_connection_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_deliveries');
        Schema::dropIfExists('platform_connections');
    }
};
