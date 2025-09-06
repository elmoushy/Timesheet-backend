<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xxx_project_managers', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('employee_id');
            $table->enum('role', ['lead', 'employee']);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->primary(['project_id', 'employee_id'], 'pk_proj_mgr');

            $table->foreign('project_id', 'fk_projmgr_proj')
                ->references('id')->on('xxx_projects')
                ->onDelete('cascade');

            $table->foreign('employee_id', 'fk_projmgr_emp')
                ->references('id')->on('xxx_employees')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_project_managers');
    }
};
