<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xxx_applications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 120)->unique();

            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign('department_id', 'fk_app_dept')
                  ->references('id')->on('xxx_departments')
                  ->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_applications');
    }
};

// 2024_06_09_000001_create_xxx_departments_table.php
// 2024_06_09_000002_create_xxx_products_table.php
// 2024_06_09_000003_create_xxx_clients_table.php
// 2024_06_09_000004_create_xxx_employees_table.php
// 2024_06_09_000005_create_xxx_applications_table.php
// 2024_06_09_000006_create_xxx_emp_phones_table.php
// 2024_06_09_000007_create_xxx_emp_emerg_contacts_table.php
// 2024_06_09_000008_create_xxx_department_managers_table.php
// 2024_06_09_000009_create_xxx_emp_application_table.php
// 2024_06_09_000010_create_xxx_projects_table.php
// 2024_06_09_000011_create_xxx_proj_products_table.php
// 2024_06_09_000012_create_xxx_project_managers_table.php
// 2024_06_09_000013_create_xxx_project_employee_assignments_table.php
// 2024_06_09_000014_create_xxx_tasks_table.php
