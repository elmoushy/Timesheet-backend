<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Replace project_type enum with project_name string column
     */
    public function up(): void
    {
        // Add the new column first
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->string('project_name', 120)->after('client_id')->unique();
        });

        // Drop the old column
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->dropColumn('project_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the original column
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->enum('project_type', ['internal', 'external', 'research', 'maintenance'])
                ->after('client_id');
        });

        // Drop the new column
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->dropColumn('project_name');
        });
    }
};
