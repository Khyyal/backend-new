<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a (non-primary) center user to one of the center's trainers. Nullable
     * because the primary user has no trainer; required-ness is validated.
     */
    public function up(): void
    {
        Schema::table('center_user_assignment', function (Blueprint $table) {
            $table->foreignId('trainer_id')
                ->nullable()
                ->after('user_id')
                ->constrained('trainers')
                ->nullOnDelete();

            $table->unique(['center_id', 'trainer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('center_user_assignment', function (Blueprint $table) {
            $table->dropUnique(['center_id', 'trainer_id']);
            $table->dropConstrainedForeignId('trainer_id');
        });
    }
};
