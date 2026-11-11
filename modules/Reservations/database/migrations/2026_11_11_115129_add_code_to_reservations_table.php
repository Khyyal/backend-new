<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A public 6-character code (HasUniqueCode) clients and center staff use to look the row up.
     * Existing rows are not backfilled; they must be given codes before this runs.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->char('code', 6)->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
