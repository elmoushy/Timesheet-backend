<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xxx_emp_application', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('application_id');

            $table->primary(['employee_id','application_id'], 'pk_emp_app');

            $table->foreign('employee_id', 'fk_ea_emp')
                  ->references('id')->on('xxx_employees')
                  ->onDelete('cascade');
            $table->foreign('application_id', 'fk_ea_app')
                  ->references('id')->on('xxx_applications')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_emp_application');
    }
};
