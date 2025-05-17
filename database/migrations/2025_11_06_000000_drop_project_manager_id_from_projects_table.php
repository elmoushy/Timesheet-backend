<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropProjectManagerIdFromProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('xxx_projects', function (Blueprint $table) {
            // First drop the foreign key constraint
            $table->dropForeign('fk_proj_mgr');

            // Then drop the column
            $table->dropColumn('project_manager_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('project_manager_id')->nullable();

            // Recreate foreign key in down method if needed
            // Note: You'll need the referenced table and column info to recreate it properly
            // $table->foreign('project_manager_id', 'fk_proj_mgr')
            //       ->references('id')->on('xxx_employees')
            //       ->onDelete('set null');
        });
    }
}
