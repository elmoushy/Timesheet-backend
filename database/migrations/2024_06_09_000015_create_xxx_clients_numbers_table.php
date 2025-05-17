<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xxx_clients_numbers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('name', 120);
            $table->string('number', 30);
            $table->enum('type', ['client', 'oracle', 'private', 'other'])->default('client');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('client_id')
                  ->references('id')
                  ->on('xxx_clients')
                  ->onDelete('cascade');

            $table->foreign('project_id')
                  ->references('id')
                  ->on('xxx_projects')
                  ->onDelete('cascade');

            $table->index('number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_clients_numbers');
    }
};
