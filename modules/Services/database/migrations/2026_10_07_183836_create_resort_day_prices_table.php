<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Services\Models\Resort;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resort_day_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Resort::class)->constrained('resorts')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->float('price');

            $table->unique(['resort_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resort_day_prices');
    }
};
