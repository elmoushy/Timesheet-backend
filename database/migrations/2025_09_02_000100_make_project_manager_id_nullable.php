<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make project_manager_id nullable and set FK to ON DELETE SET NULL.
     */
    public function up(): void
    {
        // For SQLite, we need to handle foreign key modifications differently
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite requires foreign_key_checks to be disabled
            DB::statement('PRAGMA foreign_keys = OFF');

            // Get existing data
            $projects = DB::table('xxx_projects')->get();

            // Drop and recreate table with nullable project_manager_id
            Schema::drop('xxx_projects');

            Schema::create('xxx_projects', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('client_id');
                $table->unsignedBigInteger('department_id');
                $table->date('start_date');
                $table->date('end_date');
                $table->unsignedBigInteger('project_manager_id')->nullable(); // Now nullable
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->string('project_name')->unique();

                $table->index(['start_date', 'end_date'], 'idx_proj_dates');

                $table->foreign('client_id', 'fk_proj_client')
                    ->references('id')->on('xxx_clients')
                    ->onDelete('cascade');

                $table->foreign('department_id', 'fk_proj_dept')
                    ->references('id')->on('xxx_departments')
                    ->onDelete('cascade');

                $table->foreign('project_manager_id', 'fk_proj_mgr')
                    ->references('id')->on('xxx_employees')
                    ->onDelete('set null'); // Changed to SET NULL
            });

            // Restore data, handling project_manager_id = 0 as NULL
            foreach ($projects as $project) {
                DB::table('xxx_projects')->insert([
                    'id' => $project->id,
                    'client_id' => $project->client_id,
                    'department_id' => $project->department_id,
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'project_manager_id' => ($project->project_manager_id == 0) ? null : $project->project_manager_id,
                    'notes' => $project->notes,
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                    'project_name' => $project->project_name ?? 'Unknown Project '.$project->id,
                ]);
            }

            // Re-enable foreign key checks
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            // For other databases (MySQL, PostgreSQL)
            Schema::table('xxx_projects', function (Blueprint $table) {
                $table->dropForeign('fk_proj_mgr');
            });

            Schema::table('xxx_projects', function (Blueprint $table) {
                $table->unsignedBigInteger('project_manager_id')->nullable()->change();
            });

            Schema::table('xxx_projects', function (Blueprint $table) {
                $table->foreign('project_manager_id', 'fk_proj_mgr')
                    ->references('id')->on('xxx_employees')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Revert the changes: make project_manager_id NOT NULL and restore original FK behavior.
     */
    public function down(): void
    {
        // For SQLite, we need to handle foreign key modifications differently
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            $projects = DB::table('xxx_projects')->get();

            Schema::drop('xxx_projects');

            Schema::create('xxx_projects', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('client_id');
                $table->unsignedBigInteger('department_id');
                $table->date('start_date');
                $table->date('end_date');
                $table->unsignedBigInteger('project_manager_id'); // NOT NULL again
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->string('project_name')->unique();

                $table->index(['start_date', 'end_date'], 'idx_proj_dates');

                $table->foreign('client_id', 'fk_proj_client')
                    ->references('id')->on('xxx_clients')
                    ->onDelete('cascade');

                $table->foreign('department_id', 'fk_proj_dept')
                    ->references('id')->on('xxx_departments')
                    ->onDelete('cascade');

                $table->foreign('project_manager_id', 'fk_proj_mgr')
                    ->references('id')->on('xxx_employees')
                    ->onDelete('cascade'); // Back to CASCADE
            });

            // Restore data, handling NULL as skip (since it can't be NOT NULL with NULL value)
            foreach ($projects as $project) {
                if ($project->project_manager_id !== null) {
                    DB::table('xxx_projects')->insert([
                        'id' => $project->id,
                        'client_id' => $project->client_id,
                        'department_id' => $project->department_id,
                        'start_date' => $project->start_date,
                        'end_date' => $project->end_date,
                        'project_manager_id' => $project->project_manager_id,
                        'notes' => $project->notes,
                        'created_at' => $project->created_at,
                        'updated_at' => $project->updated_at,
                        'project_name' => $project->project_name,
                    ]);
                }
            }

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('xxx_projects', function (Blueprint $table) {
                $table->dropForeign('fk_proj_mgr');
            });

            Schema::table('xxx_projects', function (Blueprint $table) {
                $table->unsignedBigInteger('project_manager_id')->nullable(false)->change();
            });

            Schema::table('xxx_projects', function (Blueprint $table) {
                $table->foreign('project_manager_id', 'fk_proj_mgr')
                    ->references('id')->on('xxx_employees')
                    ->onDelete('cascade');
            });
        }
    }
};
