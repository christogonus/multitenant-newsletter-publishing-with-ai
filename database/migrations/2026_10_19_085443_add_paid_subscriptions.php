<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paid subscriptions (OTO 4): owners lock issues to paying readers and take payment through their own Stripe or PayPal.
     */
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->boolean('paid_enabled')->default(false);
            $table->unsignedInteger('paid_monthly_cents')->nullable();
            $table->unsignedInteger('paid_yearly_cents')->nullable();
            $table->string('paid_currency', 3)->default('USD');
            $table->text('paid_perks')->nullable();
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->boolean('is_paid_only')->default(false);
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->timestamp('paid_until')->nullable();
            $table->string('paid_via')->nullable();
            $table->string('paid_plan')->nullable();
            $table->string('payment_reference')->nullable();

            $table->index(['newsletter_id', 'paid_until']);
        });

        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->text('credentials');
            $table->json('details')->nullable();
            $table->string('status')->default('connected');
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->unique(['newsletter_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');

        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropIndex(['newsletter_id', 'paid_until']);
            $table->dropColumn(['paid_until', 'paid_via', 'paid_plan', 'payment_reference']);
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('is_paid_only');
        });

        Schema::table('newsletters', function (Blueprint $table) {
            $table->dropColumn(['paid_enabled', 'paid_monthly_cents', 'paid_yearly_cents', 'paid_currency', 'paid_perks']);
        });
    }
};
