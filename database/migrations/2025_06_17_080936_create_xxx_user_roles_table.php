<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('xxx_user_roles', function (Blueprint $table) {
            $table->bigIncrements('user_roles_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id'); // employee_id
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('xxx_roles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('xxx_employees')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('xxx_employees')->onDelete('set null');

            $table->unique(['role_id', 'user_id'], 'unique_user_role');
            $table->index(['user_id', 'is_active']);
            $table->index(['role_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xxx_user_roles');
    }
};
