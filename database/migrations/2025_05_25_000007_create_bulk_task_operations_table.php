<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_task_operations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('initiated_by'); // Department manager who initiated
            $table->enum('operation_type', ['reassign', 'update_status', 'update_due_date', 'update_priority', 'bulk_delete']);
            $table->json('task_ids'); // Array of task IDs affected
            $table->json('operation_data'); // Store operation parameters (new assignee, status, etc.)
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])->default('pending');
            $table->text('notes')->nullable();
            $table->integer('total_tasks')->default(0);
            $table->integer('processed_tasks')->default(0);
            $table->integer('failed_tasks')->default(0);
            $table->json('error_log')->nullable(); // Store any errors during processing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('initiated_by', 'fk_bulk_op_mgr')
                ->references('id')->on('xxx_employees')
                ->onDelete('cascade');

            // Indexes
            $table->index(['initiated_by', 'status']);
            $table->index('operation_type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_task_operations');
    }
};
