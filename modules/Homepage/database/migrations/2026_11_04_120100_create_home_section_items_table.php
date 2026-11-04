<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_section_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('home_section_id')->constrained()->cascadeOnDelete();
            $table->string('itemable_type', 20);
            $table->unsignedBigInteger('itemable_id');
            $table->integer('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->unique(['home_section_id', 'itemable_type', 'itemable_id'], 'home_section_item_unique');
            $table->index(['home_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_items');
    }
};
