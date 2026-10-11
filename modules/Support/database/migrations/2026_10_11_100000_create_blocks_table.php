<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->morphs('blocker');
            $table->morphs('blockable');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['blocker_type', 'blocker_id', 'blockable_type', 'blockable_id'],
                'blocks_one_per_pair'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
