<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assigned_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('task_id'); // References xxx_tasks (master task)
            $table->unsignedBigInteger('assigned_to'); // Employee ID
            $table->unsignedBigInteger('assigned_by'); // Department manager who assigned
            $table->enum('status', ['to-do', 'doing', 'done', 'blocked'])->default('to-do');
            $table->integer('progress_points')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_important')->default(false);
            $table->text('notes')->nullable();
            $table->text('assignment_notes')->nullable(); // Notes from the manager when assigning
            $table->date('due_date')->nullable();
            $table->integer('estimated_hours')->nullable();
            $table->integer('actual_hours')->nullable();
            $table->enum('permission_level', ['view_only', 'edit_progress', 'full_edit'])->default('edit_progress');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->foreign('task_id', 'fk_assigned_task_master')
                  ->references('id')->on('xxx_tasks')
                  ->onDelete('cascade');

            $table->foreign('assigned_to', 'fk_assigned_task_emp')
                  ->references('id')->on('xxx_employees')
                  ->onDelete('cascade');

            $table->foreign('assigned_by', 'fk_assigned_task_mgr')
                  ->references('id')->on('xxx_employees')
                  ->onDelete('cascade');

            // Prevent duplicate assignments
            $table->unique(['task_id', 'assigned_to'], 'uk_task_employee');

            // Indexes for better performance
            $table->index(['assigned_to', 'status']);
            $table->index(['assigned_to', 'is_important']);
            $table->index(['assigned_to', 'is_pinned']);
            $table->index(['assigned_by']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assigned_tasks');
    }
};
