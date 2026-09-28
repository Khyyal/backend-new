<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_redemptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('discount_id')
                ->constrained('discounts')
                ->restrictOnDelete();

            $table->foreignId('coupon_id')
                ->nullable()
                ->constrained('coupons')
                ->restrictOnDelete();

            $table->string('used_by_type');
            $table->unsignedBigInteger('used_by_id');

            $table->string('discountable_type');
            $table->unsignedBigInteger('discountable_id');

            $table->decimal('discount_amount', 14, 2);
            $table->timestamp('redeemed_at')->useCurrent();

            $table->timestamps();

            $table->index('discount_id');
            $table->index('coupon_id');
            $table->index(['used_by_type', 'used_by_id']);
            $table->index(['discountable_type', 'discountable_id']);
            $table->index('redeemed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_redemptions');
    }
};
