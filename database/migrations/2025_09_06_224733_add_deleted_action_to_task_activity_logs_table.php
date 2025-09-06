<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the table to add new enum values
        // First, backup existing data
        $existingData = DB::table('task_activity_logs')->get();

        // Drop the table
        Schema::drop('task_activity_logs');

        // Recreate the table with updated enum
        Schema::create('task_activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('task_type', ['personal', 'project', 'assigned']);
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('employee_id');
            $table->enum('action', [
                'created',
                'updated',
                'status_changed',
                'pinned',
                'unpinned',
                'marked_important',
                'unmarked_important',
                'completed',
                'blocked',
                'deleted'
            ]);
            $table->string('field_changed')->nullable();
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

        // Restore existing data
        foreach ($existingData as $record) {
            DB::table('task_activity_logs')->insert((array) $record);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backup existing data
        $existingData = DB::table('task_activity_logs')->get();

        // Drop the table
        Schema::drop('task_activity_logs');

        // Recreate the table with original enum (without 'deleted')
        Schema::create('task_activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('task_type', ['personal', 'project', 'assigned']);
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('employee_id');
            $table->enum('action', [
                'created',
                'updated',
                'status_changed',
                'pinned',
                'unpinned',
                'marked_important',
                'unmarked_important',
                'completed',
                'blocked'
            ]);
            $table->string('field_changed')->nullable();
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

        // Restore existing data (excluding 'deleted' actions)
        foreach ($existingData as $record) {
            if ($record->action !== 'deleted') {
                DB::table('task_activity_logs')->insert((array) $record);
            }
        }
    }
};
