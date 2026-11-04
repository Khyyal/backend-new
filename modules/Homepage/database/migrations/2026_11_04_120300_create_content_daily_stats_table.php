<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_daily_stats', function (Blueprint $table): void {
            $table->date('date');
            $table->string('entity_type', 20);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);

            $table->primary(['date', 'entity_type', 'entity_id']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_daily_stats');
    }
};
