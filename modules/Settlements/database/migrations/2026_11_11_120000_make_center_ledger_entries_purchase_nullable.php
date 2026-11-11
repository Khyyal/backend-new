<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A baseline adjustment moves a center's balance without belonging to a
     * purchase.
     */
    public function up(): void
    {
        Schema::table('center_ledger_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('purchase_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('center_ledger_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('purchase_id')->nullable(false)->change();
        });
    }
};
