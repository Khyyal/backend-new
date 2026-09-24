<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centers\Enums\CenterStatus;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('centers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('city_id')
                ->constrained('cities')
                ->restrictOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->string('address')->nullable();
            $table->string('contact_phone')->nullable();

            $table->unsignedInteger('points')->default(0);

            $table->enum('status', [
                CenterStatus::VISIBLE->value,
                CenterStatus::INVISIBLE->value,
            ])->default(CenterStatus::VISIBLE->value);


            $table->timestamps();
            $table->softDeletes();

            $table->index('city_id');
            $table->index('status');
            $table->index(['city_id', 'status']);
            $table->index('points');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centers');
    }
};
