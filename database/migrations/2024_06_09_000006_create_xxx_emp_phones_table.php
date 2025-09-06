<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xxx_emp_phones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->enum('phone_type', ['mobile', 'home', 'work', 'other']);
            $table->string('phone_number', 30);
            $table->timestamps();

            $table->index('phone_number', 'idx_emp_phone_num');
            $table->foreign('employee_id', 'fk_ep_emp')
                ->references('id')->on('xxx_employees')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_emp_phones');
    }
};
