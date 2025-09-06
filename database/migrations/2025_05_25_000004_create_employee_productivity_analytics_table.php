<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_productivity_analytics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->integer('tasks_completed')->default(0);
            $table->integer('tasks_created')->default(0);
            $table->integer('total_progress_points')->default(0);
            $table->integer('hours_logged')->default(0);
            $table->integer('streak_days')->default(0); // Current streak
            $table->integer('max_streak')->default(0); // Longest streak achieved
            $table->json('weekly_burndown')->nullable(); // Store weekly burndown chart data
            $table->timestamps();

            $table->foreign('employee_id', 'fk_analytics_emp')
                ->references('id')->on('xxx_employees')
                ->onDelete('cascade');

            // Unique constraint to prevent duplicate entries per employee per day
            $table->unique(['employee_id', 'date'], 'uk_employee_analytics_date');

            // Indexes
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_productivity_analytics');
    }
};
