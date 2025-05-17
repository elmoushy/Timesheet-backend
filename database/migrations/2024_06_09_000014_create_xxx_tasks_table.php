<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xxx_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 120);
            $table->string('task_type', 255);
            $table->unsignedBigInteger('department_id');
            $table->text('description');
            $table->timestamps();

            $table->foreign('department_id', 'fk_task_dept')
                  ->references('id')->on('xxx_departments')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_tasks');
    }
};
