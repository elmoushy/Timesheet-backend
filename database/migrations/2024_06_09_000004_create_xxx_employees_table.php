<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xxx_employees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_code', 30)->unique();

            $table->string('first_name', 60);
            $table->string('middle_name', 60)->nullable();
            $table->string('last_name', 60);
            $table->string('qualification', 120)->nullable();

            $table->string('nationality', 60)->nullable();
            $table->string('region', 60)->nullable();
            $table->string('address', 255)->nullable();

            $table->string('work_email', 120)->unique();
            $table->string('personal_email', 120)->nullable();

            $table->date('birth_date');
            $table->enum('gender', ['male', 'female']);
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed']);
            $table->enum('military_status', ['completed', 'exempted', 'postponed', 'not_applicable'])->nullable();

            $table->enum('id_type', ['national_id', 'passport', 'driving_license']);
            $table->string('id_number', 60);
            $table->date('id_expiry_date');

            $table->enum('employee_type', ['full_time', 'part_time', 'contractor', 'intern']);
            $table->string('job_title', 120);
            $table->string('designation', 120)->nullable();
            $table->string('grade_level', 60)->nullable();

            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();

            $table->date('contract_start_date');
            $table->date('contract_end_date')->nullable();
            $table->enum('user_status', ['active', 'inactive'])->default('active');

            $table->string('image_path', 255)->nullable();
            $table->timestamps();

            $table->foreign('department_id', 'fk_emp_dept')
                ->references('id')->on('xxx_departments')
                ->onDelete('set null');

            $table->foreign('supervisor_id', 'fk_emp_sup')
                ->references('id')->on('xxx_employees')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_employees');
    }
};
