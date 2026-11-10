<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Support\Enums\ActivationStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_packages', function (Blueprint $table) {
            $table->enum('status', [ActivationStatus::ACTIVE->value, ActivationStatus::INACTIVE->value])
                ->default(ActivationStatus::ACTIVE->value)
                ->after('duration');
        });
    }

    public function down(): void
    {
        Schema::table('service_packages', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
