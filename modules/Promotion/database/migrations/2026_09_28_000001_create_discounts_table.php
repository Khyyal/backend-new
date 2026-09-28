<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();

            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');

            $table->json('name');
            $table->json('description')->nullable();

            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 14, 2);

            $table->enum('scope', ['all', 'specific_items'])->default('all');
            $table->enum('application_method', ['automatic', 'coupon'])->default('automatic');

            $table->decimal('minimum_amount', 14, 2)->nullable();
            $table->decimal('maximum_discount', 14, 2)->nullable();

            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();

            $table->boolean('is_stackable')->default(true);

            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();

            $table->enum('status', ['active', 'in_active'])->default('active');

            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->index('status');
            $table->index('application_method');
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
