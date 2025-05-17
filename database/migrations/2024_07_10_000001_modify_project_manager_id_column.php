<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make project_manager_id column nullable to support transition to pivot-based management.
     * This allows backward compatibility while moving to the new manager relationship structure.
     *
     * @return void
     */
    public function up(): void
    {
        // First drop the foreign key in a separate operation
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->dropForeign('fk_proj_mgr');
        });

        // Then modify the column to be nullable with unsignedBigInteger
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('project_manager_id')->nullable()->change();
        });

        // Finally re-add the foreign key in a separate operation
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->foreign('project_manager_id', 'fk_proj_mgr')
                  ->references('id')->on('xxx_employees')
                  ->onDelete('set null');
        });
    }

    /**
     * Revert the changes made to project_manager_id column.
     *
     * @return void
     */
    public function down(): void
    {
        // Drop the nullable foreign key
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->dropForeign('fk_proj_mgr');
        });

        // Make the column required again using unsignedBigInteger
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('project_manager_id')->nullable(false)->change();
        });

        // Re-add the original foreign key
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->foreign('project_manager_id', 'fk_proj_mgr')
                  ->references('id')->on('xxx_employees')
                  ->onDelete('cascade');
        });
    }
};
