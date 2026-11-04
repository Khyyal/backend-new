<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only record of what the platform owes each center (positive) or
     * the center owes the platform (negative). Entries are never changed
     * except for being attached to, or released from, a payout.
     */
    public function up(): void
    {
        Schema::create('center_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->bigInteger('amount');
            $table->timestamp('available_at');
            $table->foreignId('payout_id')->nullable()->constrained('center_payouts')->nullOnDelete();
            $table->string('idempotency_key', 100)->unique();
            $table->timestamp('created_at')->nullable();

            $table->index(['center_id', 'payout_id', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('center_ledger_entries');
    }
};
