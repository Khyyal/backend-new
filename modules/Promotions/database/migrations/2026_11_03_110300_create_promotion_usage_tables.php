<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The redemption ledger is the source of truth; the usage counters
     * (promotions.usage_count, coupons.used_count and the per-client tables)
     * are denormalised from it so limits can be enforced atomically.
     */
    public function up(): void
    {
        Schema::create('promotion_client_usages', function (Blueprint $table): void {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('used_count')->default(0);

            $table->primary(['promotion_id', 'client_id']);
        });

        Schema::create('coupon_client_usages', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('used_count')->default(0);

            $table->primary(['coupon_id', 'client_id']);
        });

        Schema::create('promotion_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('discount_amount');
            $table->string('funded_by', 20)->default('platform');
            $table->unsignedBigInteger('center_share_amount')->default(0);
            $table->string('status', 20)->default('applied');
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 50)->nullable();
            $table->timestamps();

            $table->unique(['purchase_id', 'promotion_id']);
            $table->index(['promotion_id', 'status']);
            $table->index(['client_id', 'promotion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('coupon_client_usages');
        Schema::dropIfExists('promotion_client_usages');
    }
};
