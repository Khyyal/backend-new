<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centers\Models\Center;
use Modules\Support\Enums\ActivationStatus;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug');
            $table->foreignIdFor(Center::class)->constrained('centers');
            $table->json('description');
            $table->enum('status',[ActivationStatus::ACTIVE->value, ActivationStatus::INACTIVE->value]);
            $table->string('type');
            $table->morphs('serviceable');
            $table->timestamps();
            $table->softDeletes();


            $table->unique(['slug', 'center_id']); // Ensure slug is unique per center
            $table->index('status');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
