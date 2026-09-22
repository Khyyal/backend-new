<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('stars');
            $table->text('comment')->nullable();
            $table->morphs('rateable');
            $table->morphs('rater');
            $table->unique([
                'rateable_type',
                'rateable_id',
                'rater_type',
                'rater_id',
            ]);
            $table->unique(
                [
                    'rateable_type',
                    'rateable_id',
                    'rater_type',
                    'rater_id',
                ],
                'ratings_one_per_rater'
            );

            $table->index(
                [
                    'rateable_type',
                    'rateable_id',
                    'stars',
                ],
                'ratings_rateable_stars_index'
            );

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
