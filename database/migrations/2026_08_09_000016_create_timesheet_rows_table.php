<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop table if it exists to avoid conflicts
        Schema::dropIfExists('timesheet_rows');

        Schema::create('timesheet_rows', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('timesheet_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('task_id');
            $table->decimal('hours_monday', 4, 2)->default(0);
            $table->decimal('hours_tuesday', 4, 2)->default(0);
            $table->decimal('hours_wednesday', 4, 2)->default(0);
            $table->decimal('hours_thursday', 4, 2)->default(0);
            $table->decimal('hours_friday', 4, 2)->default(0);
            $table->decimal('hours_saturday', 4, 2)->default(0);
            $table->decimal('hours_sunday', 4, 2)->default(0);

            // Using a regular column for total_hours initially
            $table->decimal('total_hours', 5, 2)->default(0);

            $table->text('achievement_note')->nullable();
            $table->timestamps();

            $table->foreign('timesheet_id')
                ->references('id')
                ->on('timesheets')
                ->onDelete('cascade');

            $table->foreign('project_id')
                ->references('id')
                ->on('xxx_projects')
                ->onDelete('cascade');

            $table->foreign('task_id')
                ->references('id')
                ->on('xxx_tasks')
                ->onDelete('cascade');

            // Index for faster queries
            $table->index('timesheet_id');
        });

        // Add trigger to calculate total_hours on insert or update
        $connection = DB::connection()->getDriverName();

        if ($connection === 'oracle') {
            // Oracle trigger syntax
            DB::unprepared('
                CREATE OR REPLACE TRIGGER calculate_total_hours_insert
                BEFORE INSERT ON timesheet_rows
                FOR EACH ROW
                BEGIN
                    :NEW.total_hours := :NEW.hours_monday + :NEW.hours_tuesday + :NEW.hours_wednesday +
                                        :NEW.hours_thursday + :NEW.hours_friday + :NEW.hours_saturday +
                                        :NEW.hours_sunday;
                END;
            ');

            DB::unprepared('
                CREATE OR REPLACE TRIGGER calculate_total_hours_update
                BEFORE UPDATE ON timesheet_rows
                FOR EACH ROW
                BEGIN
                    :NEW.total_hours := :NEW.hours_monday + :NEW.hours_tuesday + :NEW.hours_wednesday +
                                        :NEW.hours_thursday + :NEW.hours_friday + :NEW.hours_saturday +
                                        :NEW.hours_sunday;
                END;
            ');
        } elseif ($connection === 'sqlite') {
            // SQLite - skip triggers, calculate in application layer
            // The total_hours will be calculated and set by the application
        } else {
            // MySQL trigger syntax
            DB::unprepared('
                CREATE TRIGGER calculate_total_hours_insert BEFORE INSERT ON timesheet_rows
                FOR EACH ROW
                SET NEW.total_hours = NEW.hours_monday + NEW.hours_tuesday + NEW.hours_wednesday +
                                      NEW.hours_thursday + NEW.hours_friday + NEW.hours_saturday +
                                      NEW.hours_sunday;
            ');

            DB::unprepared('
                CREATE TRIGGER calculate_total_hours_update BEFORE UPDATE ON timesheet_rows
                FOR EACH ROW
                SET NEW.total_hours = NEW.hours_monday + NEW.hours_tuesday + NEW.hours_wednesday +
                                      NEW.hours_thursday + NEW.hours_friday + NEW.hours_saturday +
                                      NEW.hours_sunday;
            ');
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS calculate_total_hours_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS calculate_total_hours_update');
        Schema::dropIfExists('timesheet_rows');
    }
};
