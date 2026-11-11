<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `knight_name` is chosen by the client; `controlled` is set by the center.
     */
    public function up(): void
    {
        Schema::table('client_packages', function (Blueprint $table): void {
            $table->string('knight_name')->nullable();
            $table->boolean('controlled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('client_packages', function (Blueprint $table): void {
            $table->dropColumn(['knight_name', 'controlled']);
        });
    }
};
