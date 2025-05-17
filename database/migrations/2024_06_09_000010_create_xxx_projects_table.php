<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xxx_projects', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('client_id');
            $table->enum('project_type', ['internal','external','research','maintenance']);
            $table->unsignedBigInteger('department_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedBigInteger('project_manager_id');

            $table->string('client_contact_name', 120);
            $table->string('client_contact_number', 30);
            $table->string('oracle_contact_name', 120);
            $table->string('oracle_contact_number', 30);
            $table->string('private_contact_name', 120);
            $table->string('private_contact_number', 30);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['start_date','end_date'], 'idx_proj_dates');

            $table->foreign('client_id', 'fk_proj_client')
                  ->references('id')->on('xxx_clients')
                  ->onDelete('cascade');

            $table->foreign('department_id', 'fk_proj_dept')
                  ->references('id')->on('xxx_departments')
                  ->onDelete('cascade');

            $table->foreign('project_manager_id', 'fk_proj_mgr')
                  ->references('id')->on('xxx_employees')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_projects');
    }
};
