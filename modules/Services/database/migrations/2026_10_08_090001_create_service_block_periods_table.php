<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Services\Models\ServiceBlock;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_block_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ServiceBlock::class)->constrained('service_blocks')->cascadeOnDelete();
            $table->time('start_time');
            $table->time('end_time');

            $table->index('service_block_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_block_periods');
    }
};
