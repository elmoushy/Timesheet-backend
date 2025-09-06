<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AllowDuplicateApprovals extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        try {
            Schema::table('timesheet_approvals', function (Blueprint $table) {
                $table->dropUnique('timesheet_approvals_timesheet_id_approver_id_unique');
            });
        } catch (Exception $e) {
            // If constraint doesn't exist, continue - this is expected
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('timesheet_approvals', function (Blueprint $table) {
            $table->unique(['timesheet_id', 'approver_id']);
        });
    }
}
