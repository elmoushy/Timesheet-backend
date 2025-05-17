<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xxx_emp_emerg_contacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->string('name', 120);
            $table->string('relationship', 60);
            $table->string('phone', 30);
            $table->string('address', 255)->nullable();
            $table->timestamps();

            $table->foreign('employee_id', 'fk_eec_emp')
                  ->references('id')->on('xxx_employees')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_emp_emerg_contacts');
    }
};
