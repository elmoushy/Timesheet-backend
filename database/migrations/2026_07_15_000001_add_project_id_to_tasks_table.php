<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds project_id to tasks table to create relationship between projects and tasks.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('xxx_tasks', function (Blueprint $table) {
            // Add project_id column after department_id
            $table->unsignedBigInteger('project_id')->nullable()->after('department_id');

            // Add foreign key constraint
            $table->foreign('project_id', 'fk_task_project')
                  ->references('id')
                  ->on('xxx_projects')
                  ->onDelete('set null');

            // Add index for performance
            $table->index('project_id', 'idx_task_project');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('xxx_tasks', function (Blueprint $table) {
            // Drop the foreign key first
            $table->dropForeign('fk_task_project');

            // Drop the index
            $table->dropIndex('idx_task_project');

            // Drop the column
            $table->dropColumn('project_id');
        });
    }
};
