<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centers\Models\Center;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_type_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Center::class)->constrained('centers');
            $table->string('type');
            $table->json('terms');
            $table->timestamps();

            $table->unique(['center_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_type_terms');
    }
};
