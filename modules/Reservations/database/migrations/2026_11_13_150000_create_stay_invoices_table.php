<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stay_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('center_id')->constrained('centers');
            $table->foreignId('client_id')->constrained('clients');
            $table->string('kind');
            $table->foreignId('service_id')->nullable()->constrained('services');
            $table->foreignId('price_option_id')->nullable()->constrained('price_options');
            $table->date('date');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('total_amount');
            $table->json('metadata')->nullable();
            $table->nullableMorphs('created_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stay_invoices');
    }
};
