<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('occurrence_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('open_date');
            $table->date('close_date');
            $table->unsignedInteger('max_tickets_per_day');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
