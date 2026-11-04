<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An immutable snapshot of the platform fee charged on a purchase, so it
     * keeps its fee after the rule changes. `reversed_at` is set once the
     * purchase is cancelled or fully refunded.
     */
    public function up(): void
    {
        Schema::create('purchase_fees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('fee_rule_id')->nullable()->index();
            $table->string('channel', 20)->nullable();
            $table->unsignedBigInteger('base_amount');
            $table->unsignedInteger('percentage_bps')->default(0);
            $table->unsignedBigInteger('fixed_amount')->default(0);
            $table->unsignedBigInteger('min_amount')->nullable();
            $table->unsignedBigInteger('max_amount')->nullable();
            $table->unsignedBigInteger('amount');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_fees');
    }
};
