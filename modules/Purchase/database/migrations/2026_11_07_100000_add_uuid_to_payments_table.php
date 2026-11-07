<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * A public UUID per payment, sent to providers that require one as our
     * reference (Moyasar's given_id must be a UUID).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('payments')->whereNull('uuid')->orderBy('id')->lazyById()->each(function (object $payment): void {
            DB::table('payments')->where('id', $payment->id)->update(['uuid' => (string) Str::uuid7()]);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
