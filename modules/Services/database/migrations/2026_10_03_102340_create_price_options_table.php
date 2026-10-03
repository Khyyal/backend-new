<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Models\Service;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('price_options', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->float('price')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('unit')->default(PriceOptionUnit::OPTION->value);
            $table->foreignIdFor(Service::class)->constrained('services');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_options');
    }
};
