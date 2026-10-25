<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('key');
            $table->string('value')->nullable();

            $table->unique(['service_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_settings');
    }
};
