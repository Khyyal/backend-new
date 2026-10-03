<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();

            $table->string('gateway');
            $table->string('external_event_id');
            $table->string('event_type');

            $table->json('payload');

            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->unsignedInteger('attempts')->default(0);

            $table->timestamps();

            $table->unique(['gateway', 'external_event_id']);
            $table->index('processed_at');
            $table->index('failed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
