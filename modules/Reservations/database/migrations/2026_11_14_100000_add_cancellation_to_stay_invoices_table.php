<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stay_invoices', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('metadata');
            $table->nullableMorphs('cancelled_by');
            $table->string('cancellation_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stay_invoices', function (Blueprint $table) {
            $table->dropMorphs('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
