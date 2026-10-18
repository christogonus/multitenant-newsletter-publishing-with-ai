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
        Schema::table('domains', function (Blueprint $table) {
            $table->text('tls_error')->nullable()->after('tls_status');
            $table->unsignedSmallInteger('tls_attempts')->default(0)->after('tls_error');
            $table->timestamp('tls_requested_at')->nullable()->after('tls_attempts');
            $table->timestamp('tls_issued_at')->nullable()->after('tls_requested_at');
            $table->timestamp('tls_expires_at')->nullable()->after('tls_issued_at');
            $table->timestamp('tls_checked_at')->nullable()->after('tls_expires_at');
            $table->boolean('tenant_created')->default(false)->after('tls_checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['tls_error', 'tls_attempts', 'tls_requested_at', 'tls_issued_at', 'tls_expires_at', 'tls_checked_at', 'tenant_created']);
        });
    }
};
