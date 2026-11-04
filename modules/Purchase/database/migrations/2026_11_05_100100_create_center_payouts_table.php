<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A weekly transfer of what the platform owes a center, made of the
     * ledger entries available up to `period_end`. The bank details are
     * snapshotted when the payout is created.
     */
    public function up(): void
    {
        Schema::create('center_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained()->restrictOnDelete();
            $table->timestamp('period_end');
            $table->unsignedBigInteger('amount');
            $table->unsignedInteger('entries_count');
            $table->string('status', 20)->index();
            $table->string('iban', 34);
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['center_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('center_payouts');
    }
};
