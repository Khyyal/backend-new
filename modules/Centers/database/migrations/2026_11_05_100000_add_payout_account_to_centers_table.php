<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bank account the center's weekly payouts are transferred to.
     */
    public function up(): void
    {
        Schema::table('centers', function (Blueprint $table): void {
            $table->string('payout_iban', 34)->nullable();
            $table->string('payout_account_name')->nullable();
            $table->string('payout_bank_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('centers', function (Blueprint $table): void {
            $table->dropColumn(['payout_iban', 'payout_account_name', 'payout_bank_name']);
        });
    }
};
