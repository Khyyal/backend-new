<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centers\Models\Center;
use Modules\Services\Models\Service;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Center::class)->constrained('centers')->cascadeOnDelete();
            $table->foreignIdFor(Service::class)->nullable()->constrained('services')->cascadeOnDelete();
            $table->string('reason');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('time_scope')->default('all_day');
            $table->timestamps();

            $table->index(['center_id', 'service_id']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_blocks');
    }
};
