<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_workflow_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('timesheet_id');
            $table->enum('stage', ['pm', 'dm', 'gm', 'employee']);
            $table->enum('action', ['submitted', 'approved', 'rejected', 'reopened']);
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('acted_by');
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->foreign('timesheet_id')
                ->references('id')
                ->on('timesheets')
                ->onDelete('cascade');

            $table->foreign('acted_by')
                ->references('id')
                ->on('xxx_employees')
                ->onDelete('cascade');

            // Index for faster queries
            $table->index('timesheet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_workflow_history');
    }
};
