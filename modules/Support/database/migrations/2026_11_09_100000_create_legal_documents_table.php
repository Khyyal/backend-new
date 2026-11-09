<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('audience');
            $table->string('type');
            $table->json('content')->nullable();
            $table->timestamps();

            $table->unique(['audience', 'type']);
        });

        $now = now();

        foreach (['client', 'center'] as $audience) {
            foreach (['privacy_policy', 'terms'] as $type) {
                DB::table('legal_documents')->insert([
                    'audience' => $audience,
                    'type' => $type,
                    'content' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
