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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->timestamp('terms_accepted_at')->nullable()->after('is_admin');
            $table->foreignId('current_newsletter_id')->nullable()->after('terms_accepted_at');
            $table->string('reseller_slug')->nullable()->unique()->after('current_newsletter_id');
            $table->string('reseller_brand')->nullable()->after('reseller_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['reseller_slug']);
            $table->dropColumn(['is_admin', 'terms_accepted_at', 'current_newsletter_id', 'reseller_slug', 'reseller_brand']);
        });
    }
};
