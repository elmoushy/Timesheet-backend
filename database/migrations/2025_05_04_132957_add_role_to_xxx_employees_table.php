<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xxx_employees', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('supervisor_id');
            $table->foreign('role_id', 'fk_emp_role')
                  ->references('id')->on('xxx_roles')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('xxx_employees', function (Blueprint $table) {
            $table->dropForeign('fk_emp_role');
            $table->dropColumn('role_id');
        });
    }
};
