<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A spreadsheet export requested by a system user or a center user,
     * built in the background and downloadable until `expires_at`.
     */
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table): void {
            $table->id();
            $table->morphs('requester');
            $table->foreignId('center_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->json('filters')->nullable();
            $table->string('status', 20)->index();
            $table->string('disk', 50);
            $table->string('path');
            $table->string('file_name');
            $table->unsignedInteger('rows_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['requester_type', 'requester_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
