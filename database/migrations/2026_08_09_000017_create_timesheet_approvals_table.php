<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_approvals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('timesheet_id');
            $table->unsignedBigInteger('approver_id');
            $table->enum('approver_role', ['pm', 'dm', 'gm']);
            $table->enum('status', ['pending', 'approved', 'rejected', 'reopened', 'auto_closed'])->default('pending');
            $table->timestamp('acted_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            
            $table->unique(['timesheet_id', 'approver_id']);
            
            $table->foreign('timesheet_id')
                  ->references('id')
                  ->on('timesheets')
                  ->onDelete('cascade');
                  
            $table->foreign('approver_id')
                  ->references('id')
                  ->on('xxx_employees')
                  ->onDelete('cascade');
                  
            // Index for faster queries
            $table->index('timesheet_id');
            $table->index('approver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_approvals');
    }
};
