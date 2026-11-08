<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per day of platform-wide values that cannot be summed from the
 * per-center facts: distinct counts over trailing windows and end-of-day
 * snapshots. Center-state and payout snapshot columns are only known for the
 * day they were built on, so they are null for backfilled history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily_platform_stats', function (Blueprint $table): void {
            $table->date('date')->primary();

            $table->unsignedInteger('signups')->default(0);
            $table->unsignedInteger('new_buyers')->default(0);
            $table->unsignedInteger('active_buyers_1d')->default(0);
            $table->unsignedInteger('active_buyers_7d')->default(0);
            $table->unsignedInteger('active_buyers_30d')->default(0);

            $table->unsignedInteger('centers_total')->default(0);
            $table->unsignedInteger('new_centers')->default(0);
            $table->unsignedInteger('active_centers_30d')->default(0);
            $table->unsignedInteger('centers_active')->nullable();
            $table->unsignedInteger('centers_pending')->nullable();
            $table->unsignedInteger('centers_blocked')->nullable();

            $table->unsignedInteger('subscriptions_active')->default(0);
            $table->unsignedInteger('subscriptions_trialing')->default(0);
            $table->unsignedInteger('subscriptions_new')->default(0);
            $table->unsignedInteger('subscriptions_cancelled')->default(0);
            $table->unsignedBigInteger('mrr')->default(0);

            $table->bigInteger('payout_unpaid_balance')->nullable();
            $table->bigInteger('payout_on_hold')->nullable();
            $table->unsignedBigInteger('payout_pending')->nullable();

            $table->timestamp('built_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_platform_stats');
    }
};
