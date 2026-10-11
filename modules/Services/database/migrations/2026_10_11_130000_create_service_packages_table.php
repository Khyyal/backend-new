<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('price_option_id')->unique()->constrained('price_options')->cascadeOnDelete();
            $table->unsignedInteger('count');
            $table->float('price');
            $table->unsignedSmallInteger('duration');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_packages');
    }
};
