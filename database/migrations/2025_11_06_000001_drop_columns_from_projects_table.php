<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropColumnsFromProjectsTable extends Migration
{
    /**
     * Remove contact columns from projects table.
     * These fields are being replaced by the xxx_clients_numbers table
     * which offers more flexibility for contact management.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->dropColumn([
                'client_contact_name',
                'client_contact_number',
                'oracle_contact_name',
                'oracle_contact_number',
                'private_contact_name',
                'private_contact_number'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     * Recreate the contact columns with their original specifications.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('xxx_projects', function (Blueprint $table) {
            $table->string('client_contact_name', 120);
            $table->string('client_contact_number', 30);
            $table->string('oracle_contact_name', 120);
            $table->string('oracle_contact_number', 30);
            $table->string('private_contact_name', 120);
            $table->string('private_contact_number', 30);
        });
    }
}
