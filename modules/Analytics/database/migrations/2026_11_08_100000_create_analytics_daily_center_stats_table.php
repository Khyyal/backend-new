<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive daily facts per merchant. `center_id` 0 is the platform merchant
 * (subscriptions and memberships the platform sells itself).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily_center_stats', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('center_id');
            $table->unsignedBigInteger('city_id')->nullable();

            $table->unsignedInteger('purchases_count')->default(0);
            $table->unsignedInteger('all_purchases_count')->default(0);
            $table->unsignedBigInteger('gmv')->default(0);
            $table->unsignedBigInteger('discount_platform')->default(0);
            $table->unsignedBigInteger('discount_center')->default(0);
            $table->bigInteger('net_fee')->default(0);
            $table->bigInteger('subsidy')->default(0);
            $table->bigInteger('refunds')->default(0);
            $table->unsignedBigInteger('provider_fees')->default(0);
            $table->unsignedInteger('new_buyers')->default(0);

            $table->unsignedInteger('bookings_made')->default(0);
            $table->unsignedInteger('tickets_booked')->default(0);
            $table->unsignedInteger('reservations_scheduled')->default(0);
            $table->unsignedInteger('reservations_completed')->default(0);
            $table->unsignedInteger('reservations_no_show')->default(0);
            $table->unsignedInteger('reservations_cancelled')->default(0);
            $table->unsignedInteger('cancellations_by_client')->default(0);

            $table->unsignedInteger('payments_attempted')->default(0);
            $table->unsignedInteger('payments_succeeded')->default(0);
            $table->unsignedInteger('payments_failed')->default(0);

            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('ratings_stars_sum')->default(0);

            $table->timestamp('built_at');

            $table->unique(['date', 'center_id']);
            $table->index(['center_id', 'date']);
            $table->index(['city_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_center_stats');
    }
};
