<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Support\Enums\ActivationStatus;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('center_user_assignment', function (Blueprint $table) {
            $table->id();

            $table->foreignId('center_id')
                ->constrained('centers')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('center_users')
                ->cascadeOnDelete();

            $table->enum('status', [
                ActivationStatus::ACTIVE->value,
                ActivationStatus::INACTIVE->value,
            ])->default(ActivationStatus::ACTIVE->value);

            $table->date('joined_at')->useCurrent();

            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->unique(['center_id', 'user_id']);

            $table->index(['center_id', 'status']);
            $table->index(['center_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('center_user_assignment');
    }
};
