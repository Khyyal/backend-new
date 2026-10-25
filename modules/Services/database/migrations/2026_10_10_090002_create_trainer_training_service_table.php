<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Services\Models\Trainer;
use Modules\Services\Models\TrainingService;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_training_service', function (Blueprint $table) {
            $table->foreignIdFor(Trainer::class)->constrained('trainers')->cascadeOnDelete();
            $table->foreignIdFor(TrainingService::class)->constrained('training_services')->cascadeOnDelete();

            $table->primary(['trainer_id', 'training_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_training_service');
    }
};
