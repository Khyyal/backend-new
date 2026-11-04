<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->foreignId('service_package_id')->constrained('service_packages')->cascadeOnDelete();
            $table->foreignId('price_option_id')->constrained('price_options')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('trainers')->nullOnDelete();

            $table->unique(['membership_id', 'service_package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_packages');
    }
};
