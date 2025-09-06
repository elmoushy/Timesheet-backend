<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('project_assignment_id'); // References xxx_project_employee_assignments
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('status', ['to-do', 'doing', 'done', 'blocked'])->default('to-do');
            $table->integer('progress_points')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_important')->default(false);
            $table->text('notes')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('estimated_hours')->nullable();
            $table->integer('actual_hours')->nullable();
            $table->boolean('auto_generated')->default(true); // True if auto-pulled from project assignment
            $table->timestamps();

            $table->foreign('employee_id', 'fk_project_task_emp')
                ->references('id')->on('xxx_employees')
                ->onDelete('cascade');

            $table->foreign('project_assignment_id', 'fk_project_task_assignment')
                ->references('id')->on('xxx_project_employee_assignments')
                ->onDelete('cascade');

            // Indexes for better performance
            $table->index(['employee_id', 'status']);
            $table->index(['employee_id', 'is_important']);
            $table->index(['employee_id', 'is_pinned']);
            $table->index('due_date');
            $table->index('project_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
