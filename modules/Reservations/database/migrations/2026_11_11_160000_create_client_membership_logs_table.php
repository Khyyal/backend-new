<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only log of what happened on a membership: guests a member invited and center check-ins.
     */
    public function up(): void
    {
        Schema::create('client_membership_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_membership_id')->constrained('client_memberships')->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('guest_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->nullableMorphs('actor');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['client_membership_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_membership_logs');
    }
};
