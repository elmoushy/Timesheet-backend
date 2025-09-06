<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_chats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('timesheet_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('sender_id');
            $table->enum('sender_role', ['employee', 'pm', 'dm', 'gm', 'admin']);
            $table->text('message');
            $table->timestamps();

            $table->foreign('timesheet_id')
                ->references('id')
                ->on('timesheets')
                ->onDelete('cascade');

            $table->foreign('parent_id')
                ->references('id')
                ->on('timesheet_chats')
                ->onDelete('cascade');

            $table->foreign('sender_id')
                ->references('id')
                ->on('xxx_employees')
                ->onDelete('cascade');

            // Index for faster queries
            $table->index('timesheet_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_chats');
    }
};
