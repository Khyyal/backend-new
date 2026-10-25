<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Purchase\Enums\PaymentStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('status', array_column(PaymentStatus::cases(), 'value'))
                ->default(PaymentStatus::Pending->value)
                ->change();

            $table->decimal('refunded_amount', 14, 2)->default(0)->after('amount');
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['refunded_amount', 'refunded_at']);

            $table->enum('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Processing->value,
                PaymentStatus::Succeeded->value,
                PaymentStatus::Failed->value,
                PaymentStatus::Cancelled->value,
                PaymentStatus::Expired->value,
            ])->default(PaymentStatus::Pending->value)->change();
        });
    }
};
