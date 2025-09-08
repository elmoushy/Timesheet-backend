<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('xxx_employees', function (Blueprint $table) {
            $table->string('employee_type', 120)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xxx_employees', function (Blueprint $table) {
            $table->enum('employee_type', ['full_time', 'part_time', 'contractor', 'intern'])->change();
        });
    }
};
