<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixProjectEmployeeAssignmentsForeignKey extends Migration
{
    /**
     * Run the migration.
     *
     * @return void
     */
    public function up()
    {
        // First, check if the foreign key exists and drop it
        $foreignKeys = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_NAME = 'project_tasks'
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND CONSTRAINT_SCHEMA = DATABASE()"
        );

        // Drop the existing foreign key constraint
        foreach ($foreignKeys as $foreignKey) {
            if (strpos($foreignKey->CONSTRAINT_NAME, 'fk_project_task_assignment') !== false) {
                Schema::table('project_tasks', function (Blueprint $table) use ($foreignKey) {
                    $table->dropForeign($foreignKey->CONSTRAINT_NAME);
                });
            }
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
