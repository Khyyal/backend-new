<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('action_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->morphs('actionable');
            $table->morphs('actor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_logs');
    }
};
