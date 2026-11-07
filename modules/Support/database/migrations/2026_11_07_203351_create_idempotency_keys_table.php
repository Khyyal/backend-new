<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id');
            $table->string('key', 100);
            $table->string('route');
            $table->char('fingerprint', 64);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();

            $table->unique(['actor_type', 'actor_id', 'key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
