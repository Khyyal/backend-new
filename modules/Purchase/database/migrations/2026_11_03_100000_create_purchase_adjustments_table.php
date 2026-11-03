<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An immutable snapshot of each discount applied to a purchase, so the
     * purchase keeps explaining its price after the promotion changes.
     */
    public function up(): void
    {
        Schema::create('purchase_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->unsignedBigInteger('promotion_id')->nullable()->index();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('code', 32)->nullable();
            $table->json('label');
            $table->string('discount_type', 20);
            $table->unsignedBigInteger('discount_value');
            $table->unsignedBigInteger('amount');
            $table->string('funded_by', 20);
            $table->unsignedBigInteger('center_share_amount')->default(0);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_adjustments');
    }
};
