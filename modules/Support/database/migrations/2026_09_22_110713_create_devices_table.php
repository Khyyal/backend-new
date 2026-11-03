<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_identifier');
            $table->string('fcm_token')->nullable();
            $table->string('platform')->nullable();
            $table->string('locale')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->nullableMorphs('deviceable');
            $table->unique(['deviceable_id', 'deviceable_type', 'device_identifier'], 'devices_deviceable_identifier_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
