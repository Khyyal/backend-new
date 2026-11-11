<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The true financial figures of a migrated center as of `cutoff_at`, set
     * by finance, next to what the migrated data said (`migrated_*`, frozen
     * at the first confirmation). A row with no `confirmed_at` is pending
     * and blocks the center's payouts. Amounts are halalas.
     */
    public function up(): void
    {
        Schema::create('center_financial_baselines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamp('cutoff_at')->nullable();
            $table->bigInteger('target_balance')->nullable();
            $table->unsignedBigInteger('target_platform_fees')->nullable();
            $table->unsignedBigInteger('target_paid_out')->nullable();
            $table->unsignedBigInteger('target_gross_sales')->nullable();
            $table->unsignedBigInteger('target_refunds')->nullable();
            $table->bigInteger('migrated_balance')->nullable();
            $table->bigInteger('migrated_platform_fees')->nullable();
            $table->bigInteger('migrated_gross_sales')->nullable();
            $table->bigInteger('migrated_refunds')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamps();

            $table->index(['confirmed_at', 'cutoff_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('center_financial_baselines');
    }
};
