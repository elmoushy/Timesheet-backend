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
        Schema::create('xx_support', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->text('message');
            $table->unsignedBigInteger('support_image_id')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('xxx_employees')->onDelete('cascade');
            $table->foreign('support_image_id')->references('id')->on('support_images')->onDelete('set null');

            // Indexes
            $table->index('employee_id');
            $table->index('support_image_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xx_support');
    }
};
