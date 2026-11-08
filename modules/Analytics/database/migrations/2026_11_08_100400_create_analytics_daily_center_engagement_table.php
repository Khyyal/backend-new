<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Client engagement with centers, counted live per day. Center, map and
     * WhatsApp clicks sit on the center's row (service_id 0); service clicks
     * on the service's row. No client identity is stored.
     */
    public function up(): void
    {
        Schema::create('analytics_daily_center_engagement', function (Blueprint $table): void {
            $table->date('date');
            $table->unsignedBigInteger('center_id');
            $table->unsignedBigInteger('service_id')->default(0);

            $table->unsignedInteger('center_clicks')->default(0);
            $table->unsignedInteger('service_clicks')->default(0);
            $table->unsignedInteger('map_clicks')->default(0);
            $table->unsignedInteger('whatsapp_clicks')->default(0);

            $table->primary(['date', 'center_id', 'service_id']);
            $table->index(['center_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_center_engagement');
    }
};
