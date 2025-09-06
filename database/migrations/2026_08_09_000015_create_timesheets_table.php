<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('overall_status', ['draft', 'in_review', 'approved', 'rejected', 'reopened']);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'period_start']);

            $table->foreign('employee_id')
                ->references('id')
                ->on('xxx_employees')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
