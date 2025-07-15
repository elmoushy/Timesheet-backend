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
        Schema::create('xxx_page_role_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('role_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('xxx_pages')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('xxx_roles')->onDelete('cascade');

            $table->unique(['page_id', 'role_id'], 'unique_page_role');
            $table->index(['page_id', 'is_active']);
            $table->index(['role_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xxx_page_role_permissions');
    }
};
