<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add password column to xxx_employees table.
     * This enables authentication capabilities for employee accounts.
     */
    public function up(): void
    {
        Schema::table('xxx_employees', function (Blueprint $table) {
            $table->string('password', 255)->nullable()->after('user_status');
        });
    }

    /**
     * Reverse the migrations to remove the password column.
     */
    public function down(): void
    {
        Schema::table('xxx_employees', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
