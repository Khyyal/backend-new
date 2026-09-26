<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();

            $table->string('buyer_type');
            $table->unsignedBigInteger('buyer_id');

            $table->string('merchant_type');
            $table->unsignedBigInteger('merchant_id');

            $table->enum('source', [
                PurchaseSource::Client->value,
                PurchaseSource::Center->value,
                PurchaseSource::System->value,
            ]);

            $table->enum('status', [
                PurchaseStatus::Pending->value,
                PurchaseStatus::Confirmed->value,
                PurchaseStatus::Cancelled->value,
                PurchaseStatus::Completed->value,
            ])->default(PurchaseStatus::Pending->value);

            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['buyer_type', 'buyer_id']);
            $table->index(['merchant_type', 'merchant_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
