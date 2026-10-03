<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_limits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_id')
                ->constrained('plans')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('limit_id')
                ->constrained('limits')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->integer('value');

            $table->timestamps();

            $table->unique(['plan_id', 'limit_id'], 'plan_limit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_limits');
    }
};
