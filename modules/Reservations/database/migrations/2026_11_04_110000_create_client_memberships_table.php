<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->unsignedBigInteger('price');
            $table->string('status')->default('pending');
            $table->foreignId('visit_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->unsignedInteger('max_visits_per_day')->nullable();
            $table->unsignedSmallInteger('duration');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });

        Schema::table('client_packages', function (Blueprint $table) {
            $table->foreignId('client_membership_id')->nullable()->after('price_option_id')->constrained('client_memberships')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->after('client_membership_id')->constrained('trainers')->nullOnDelete();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('client_membership_id')->nullable()->after('client_package_id')->constrained('client_memberships')->nullOnDelete();

            $table->index(['client_membership_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['client_membership_id', 'date']);
            $table->dropConstrainedForeignId('client_membership_id');
        });

        Schema::table('client_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trainer_id');
            $table->dropConstrainedForeignId('client_membership_id');
        });

        Schema::dropIfExists('client_memberships');
    }
};
