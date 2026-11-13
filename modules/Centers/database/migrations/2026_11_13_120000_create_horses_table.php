<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('room');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['center_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horses');
    }
};
