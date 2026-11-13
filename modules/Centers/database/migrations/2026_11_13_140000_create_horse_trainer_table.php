<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horse_trainer', function (Blueprint $table) {
            $table->foreignId('horse_id')->constrained('horses')->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('trainers')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['horse_id', 'trainer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horse_trainer');
    }
};
