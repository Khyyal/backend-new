<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_monthly_client_stats', function (Blueprint $table): void {
            $table->date('month')->primary();
            $table->unsignedInteger('signups')->default(0);
            $table->unsignedInteger('buyers')->default(0);
            $table->unsignedInteger('new_buyers')->default(0);
            $table->unsignedInteger('repeat_buyers')->default(0);
            $table->timestamp('built_at');
        });

        Schema::create('analytics_cohort_retention', function (Blueprint $table): void {
            $table->date('cohort_month');
            $table->unsignedTinyInteger('months_since');
            $table->unsignedInteger('cohort_size');
            $table->unsignedInteger('active_buyers');
            $table->timestamp('built_at');

            $table->primary(['cohort_month', 'months_since']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_cohort_retention');
        Schema::dropIfExists('analytics_monthly_client_stats');
    }
};
