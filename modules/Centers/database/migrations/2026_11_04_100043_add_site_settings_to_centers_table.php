<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            $table->json('social_links')->nullable()->after('contact_phone');
            $table->json('site_theme')->nullable()->after('social_links');
            $table->boolean('custom_site_enabled')->default(false)->after('site_theme');
            $table->string('domain')->nullable()->unique()->after('custom_site_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            $table->dropUnique(['domain']);
            $table->dropColumn(['social_links', 'site_theme', 'custom_site_enabled', 'domain']);
        });
    }
};
