<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('home_section_id')->nullable()->constrained()->nullOnDelete();
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->json('cta_label')->nullable();
            $table->string('action_type', 20)->default('none');
            $table->unsignedBigInteger('action_target_id')->nullable();
            $table->string('action_url', 2048)->nullable();
            $table->foreignId('center_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('system_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['home_section_id', 'status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
