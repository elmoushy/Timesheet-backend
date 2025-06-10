<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_workload_capacity', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->integer('weekly_capacity_hours')->default(40); // Standard work week
            $table->integer('current_planned_hours')->default(0);
            $table->date('week_start_date');
            $table->decimal('workload_percentage', 5, 2)->default(0.00); // Calculated percentage
            $table->enum('workload_status', ['under_utilized', 'optimal', 'over_loaded', 'critical'])->default('optimal');
            $table->timestamps();

            $table->foreign('employee_id', 'fk_workload_emp')
                  ->references('id')->on('xxx_employees')
                  ->onDelete('cascade');

            // Unique constraint per employee per week
            $table->unique(['employee_id', 'week_start_date'], 'uk_employee_workload_week');

            // Indexes
            $table->index(['employee_id', 'week_start_date']);
            $table->index('workload_status');
            $table->index('week_start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_workload_capacity');
    }
};
