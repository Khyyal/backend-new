<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['client_packages', 'client_memberships'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('expiry_reminder_sent_at')->nullable()->after('expires_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['client_packages', 'client_memberships'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('expiry_reminder_sent_at');
            });
        }
    }
};
