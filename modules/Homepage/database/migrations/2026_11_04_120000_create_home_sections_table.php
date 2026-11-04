<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 30);
            $table->json('title')->nullable();
            $table->json('subtitle')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('config')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('system_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sections');
    }
};
