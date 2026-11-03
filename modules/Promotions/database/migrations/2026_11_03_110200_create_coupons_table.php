<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_client_usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_assigned_only')->default(false);
            $table->string('batch_id', 36)->nullable()->index();
            $table->nullableMorphs('creator');
            $table->timestamps();

            $table->index(['promotion_id', 'status']);
        });

        Schema::create('coupon_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();

            $table->unique(['coupon_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_assignments');
        Schema::dropIfExists('coupons');
    }
};
