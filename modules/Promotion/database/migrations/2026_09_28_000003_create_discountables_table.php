<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discountables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('discount_id')
                ->constrained('discounts')
                ->cascadeOnDelete();

            $table->string('discountable_type');
            $table->unsignedBigInteger('discountable_id');

            $table->timestamps();

            $table->unique([
                'discount_id',
                'discountable_type',
                'discountable_id',
            ], 'discountables_unique');

            $table->index(['discountable_type', 'discountable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discountables');
    }
};
