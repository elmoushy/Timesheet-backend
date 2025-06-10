<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('task_type', ['personal', 'project', 'assigned']); // Which table the task belongs to
            $table->unsignedBigInteger('task_id'); // ID in the respective table
            $table->unsignedBigInteger('employee_id'); // Who performed the action
            $table->enum('action', ['created', 'updated', 'status_changed', 'pinned', 'unpinned', 'marked_important', 'unmarked_important', 'completed', 'blocked']);
            $table->string('field_changed')->nullable(); // Which field was changed
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('performed_at')->useCurrent();
            $table->timestamps();

            $table->foreign('employee_id', 'fk_activity_emp')
                  ->references('id')->on('xxx_employees')
                  ->onDelete('cascade');

            // Indexes for better performance
            $table->index(['task_type', 'task_id']);
            $table->index(['employee_id', 'performed_at']);
            $table->index('action');
            $table->index('performed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activity_logs');
    }
};
