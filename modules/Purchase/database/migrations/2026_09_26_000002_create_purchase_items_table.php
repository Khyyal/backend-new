<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');

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
