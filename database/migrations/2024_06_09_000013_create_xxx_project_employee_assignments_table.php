<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xxx_project_employee_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('employee_id');
            $table->enum('department_approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('response_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'employee_id'], 'uk_proj_emp_assignment');

            $table->foreign('project_id', 'fk_pea_proj')
                ->references('id')->on('xxx_projects')
                ->onDelete('cascade');

            $table->foreign('employee_id', 'fk_pea_emp')
                ->references('id')->on('xxx_employees')
                ->onDelete('cascade');

            $table->foreign('requested_by', 'fk_pea_req')
                ->references('id')->on('xxx_employees')
                ->onDelete('cascade');

            $table->foreign('approved_by', 'fk_pea_appr')
                ->references('id')->on('xxx_employees')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_project_employee_assignments');
    }
};
