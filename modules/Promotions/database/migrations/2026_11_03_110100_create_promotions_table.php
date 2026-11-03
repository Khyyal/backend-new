<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money columns are minor units (halalas); percentage values are basis points.
     * A null center_id makes the promotion platform-wide.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('center_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('badge_label')->nullable();
            $table->string('trigger', 20)->default('automatic');
            $table->string('scope', 20);
            $table->string('discount_type', 20);
            $table->unsignedBigInteger('discount_value');
            $table->unsignedBigInteger('max_discount_amount')->nullable();
            $table->unsignedBigInteger('min_subtotal_amount')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->integer('priority')->default(0);
            $table->string('stacking', 20)->default('stackable');
            $table->string('funded_by', 20)->default('platform');
            $table->unsignedSmallInteger('center_share_bps')->default(0);
            $table->unsignedInteger('total_usage_limit')->nullable();
            $table->unsignedInteger('per_client_usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('first_booking_only')->default(false);
            $table->nullableMorphs('creator');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['center_id', 'status']);
            $table->index(['trigger', 'status']);
        });

        Schema::create('promotion_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->string('mode', 10)->default('include');

            $table->unique(['promotion_id', 'target_type', 'target_id']);
        });

        Schema::create('promotion_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');

            $table->index(['promotion_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_schedules');
        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotions');
    }
};
