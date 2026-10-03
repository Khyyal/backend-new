<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_id')
                ->constrained('purchases')
                ->restrictOnDelete();

            $table->string('purchasable_type');
            $table->unsignedBigInteger('purchasable_id');

            $table->string('name');

            $table->unsignedInteger('quantity')->default(1);

            $table->decimal('unit_price', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('purchase_id');
            $table->index(['purchasable_type', 'purchasable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
