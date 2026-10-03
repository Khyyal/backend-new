<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_id')
                ->constrained('purchases')
                ->restrictOnDelete();

            $table->decimal('amount', 14, 2);

            $table->enum('method', [
                PaymentMethod::Online->value,
                PaymentMethod::CashOnArrival->value,
                PaymentMethod::Manual->value,
            ]);

            $table->enum('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Processing->value,
                PaymentStatus::Succeeded->value,
                PaymentStatus::Failed->value,
                PaymentStatus::Cancelled->value,
                PaymentStatus::Expired->value,
            ])->default(PaymentStatus::Pending->value);

            $table->string('payment_company')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('payment_order_id')->nullable();

            $table->json('provider_data')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index('purchase_id');
            $table->index('status');
            $table->index('payment_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
