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
        Schema::create('support_images', function (Blueprint $table) {
            $table->id();
            $table->longText('image'); // BLOB storage for image data
            $table->string('mime_type', 100)->nullable(); // Store MIME type
            $table->integer('size')->nullable(); // Store file size
            $table->string('original_name', 255)->nullable(); // Store original filename
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_images');
    }
};
