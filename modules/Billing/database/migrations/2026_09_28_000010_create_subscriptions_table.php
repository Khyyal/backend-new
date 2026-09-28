<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->string('subscribable_type');
            $table->unsignedBigInteger('subscribable_id');

            $table->foreignId('plan_id')
                ->constrained('plans')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->enum('status', ['active', 'canceled', 'expired', 'trialing'])->default('trialing');

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('ends_at');
            $table->index('plan_id');
            $table->index(['subscribable_type', 'subscribable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
