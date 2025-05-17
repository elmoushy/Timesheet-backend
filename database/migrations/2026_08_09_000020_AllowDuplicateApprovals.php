<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AllowDuplicateApprovals extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('timesheet_approvals', function (Blueprint $table) {
            $table->dropUnique('timesheet_approvals_timesheet_id_approver_id_unique');
        });
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
