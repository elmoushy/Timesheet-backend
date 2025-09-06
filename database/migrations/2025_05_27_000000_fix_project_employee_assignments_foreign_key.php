<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixProjectEmployeeAssignmentsForeignKey extends Migration
{
    /**
     * Run the migration.
     *
     * @return void
     */
    public function up()
    {
        // For Oracle, we'll use a try-catch approach to handle the constraint
        try {
            // Try to drop the existing foreign key constraint if it exists
            Schema::table('project_tasks', function (Blueprint $table) {
                $table->dropForeign('fk_project_task_assignment');
            });
        } catch (Exception $e) {
            // If constraint doesn't exist, continue
        }

        // Re-add the correct foreign key constraint
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->foreign('project_assignment_id', 'fk_project_task_assignment')
                ->references('id')
                ->on('xxx_project_employee_assignments')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down()
    {
        // First, drop the foreign key we added
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropForeign('fk_project_task_assignment');
        });

        // Re-add the original foreign key if needed
        // This is just a placeholder - in a real migration down() method,
        // you would restore the exact original state
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->foreign('project_assignment_id', 'fk_project_task_assignment')
                ->references('id')
                ->on('xxx_project_employee_assignments')
                ->onDelete('cascade');
        });
    }
}
