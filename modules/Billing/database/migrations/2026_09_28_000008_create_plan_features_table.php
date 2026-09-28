<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_id')
                ->constrained('plans')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('feature_id')
                ->constrained('features')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->boolean('enabled')->default(true);

            $table->timestamps();

            $table->unique(['plan_id', 'feature_id'], 'plan_feature_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
