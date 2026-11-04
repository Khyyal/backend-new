<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform fee charged to centers on their purchases. A rule without
     * a center is the platform default; a rule without a channel is the
     * fallback. Money columns are minor units (halalas); percentages are
     * basis points.
     */
    public function up(): void
    {
        Schema::create('fee_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 20)->nullable();
            $table->unsignedInteger('percentage_bps')->default(0);
            $table->unsignedBigInteger('fixed_amount')->default(0);
            $table->unsignedBigInteger('min_amount')->nullable();
            $table->unsignedBigInteger('max_amount')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['center_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_rules');
    }
};
