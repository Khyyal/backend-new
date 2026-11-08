<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily facts per merchant split by one dimension (channel, service type,
 * payment method/company/type, promotion, coupon). What `count`, `amount`,
 * `success_count` and `related_amount` mean depends on the dimension; see
 * BreakdownDimension.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily_breakdowns', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('center_id');
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('dimension', 30);
            $table->string('dimension_key', 64);
            $table->unsignedInteger('count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedBigInteger('related_amount')->default(0);
            $table->timestamp('built_at');

            $table->unique(['date', 'center_id', 'dimension', 'dimension_key'], 'analytics_breakdowns_unique');
            $table->index(['dimension', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_breakdowns');
    }
};
