<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->morphs('schedulable');
            $table->unsignedTinyInteger('day_of_week');

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->softDeletes();

            $table->timestamps();

            $table->index([
                'schedulable_type',
                'schedulable_id',
                'day_of_week',
            ]);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
