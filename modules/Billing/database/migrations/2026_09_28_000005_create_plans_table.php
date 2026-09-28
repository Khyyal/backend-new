<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            $table->json('name');
            $table->string('slug')->unique();
            $table->json('description')->nullable();

            $table->decimal('price', 14, 2)->default(0);

            $table->enum('billing_interval', ['monthly', 'yearly']);

            $table->json('display_features')->nullable();

            $table->enum('status', ['active', 'in_active'])->default('active');

            $table->unsignedInteger('trial_days')->default(0);

            $table->timestamps();

            $table->index('status');
            $table->index('billing_interval');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
