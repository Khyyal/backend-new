<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('center_payouts', function (Blueprint $table): void {
            $table->string('iban', 34)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('center_payouts', function (Blueprint $table): void {
            $table->string('iban', 34)->nullable(false)->change();
        });
    }
};
