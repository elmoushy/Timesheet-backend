<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('xxx_department_managers')) {
            Schema::create('xxx_department_managers', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id');
                $table->unsignedBigInteger('employee_id');
                $table->boolean('is_primary')->default(false);
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->primary(['department_id', 'employee_id'], 'pk_dept_mgr');

                $table->foreign('department_id', 'fk_deptmgr_dept')
                    ->references('id')->on('xxx_departments')
                    ->onDelete('cascade');

                $table->foreign('employee_id', 'fk_deptmgr_emp')
                    ->references('id')->on('xxx_employees')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_department_managers');
    }
};
