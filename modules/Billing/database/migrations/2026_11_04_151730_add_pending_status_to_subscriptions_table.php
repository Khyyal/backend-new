<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A paid checkout holds its subscription as pending until the purchase is confirmed.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'active', 'canceled', 'expired', 'trialing'])
                ->default('trialing')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->enum('status', ['active', 'canceled', 'expired', 'trialing'])
                ->default('trialing')
                ->change();
        });
    }
};
