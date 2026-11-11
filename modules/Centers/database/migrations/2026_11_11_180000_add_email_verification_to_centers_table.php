<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Email verification by a 6-digit code (stored hashed). Existing centers stay unverified.
     */
    public function up(): void
    {
        Schema::table('centers', function (Blueprint $table): void {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('email_verification_code')->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_code');
            $table->timestamp('email_verification_sent_at')->nullable()->after('email_verification_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('centers', function (Blueprint $table): void {
            $table->dropColumn(['email_verified_at', 'email_verification_code', 'email_verification_expires_at', 'email_verification_sent_at']);
        });
    }
};
