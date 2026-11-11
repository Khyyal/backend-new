<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The single, admin-edited layout of printed payout invoices. Its one row
     * is created here and only ever updated.
     */
    public function up(): void
    {
        Schema::create('payout_invoice_templates', function (Blueprint $table): void {
            $table->id();
            $table->json('company_name')->nullable();
            $table->json('company_address')->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('commercial_registration', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('accent_color', 7)->default('#1F4E79');
            $table->json('header_note')->nullable();
            $table->json('footer_note')->nullable();
            $table->boolean('show_bank_details')->default(true);
            $table->boolean('show_entry_summary')->default(true);
            $table->boolean('show_entries_table')->default(true);
            $table->boolean('show_signature')->default(true);
            $table->timestamps();
        });

        DB::table('payout_invoice_templates')->insert([
            'company_name' => json_encode(['en' => 'Khyyal', 'ar' => 'خيال'], JSON_UNESCAPED_UNICODE),
            'accent_color' => '#1F4E79',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_invoice_templates');
    }
};
