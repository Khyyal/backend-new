<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->unsignedInteger('max_visits_per_day')->nullable();
            $table->unsignedSmallInteger('duration');
            $table->string('color', 7);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
