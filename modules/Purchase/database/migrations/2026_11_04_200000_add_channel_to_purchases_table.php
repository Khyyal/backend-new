<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the purchase was made (see PurchaseChannel); null when unknown.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->string('channel', 20)->nullable()->index()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropIndex(['channel']);
            $table->dropColumn('channel');
        });
    }
};
