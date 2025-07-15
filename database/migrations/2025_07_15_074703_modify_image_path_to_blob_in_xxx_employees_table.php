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
        Schema::table('xxx_employees', function (Blueprint $table) {
            // Drop the existing image_path column
            $table->dropColumn('image_path');
        });

        Schema::table('xxx_employees', function (Blueprint $table) {
            // Add the new BLOB column for image data
            $table->binary('image_path')->nullable()->after('user_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xxx_employees', function (Blueprint $table) {
            // Drop the BLOB column
            $table->dropColumn('image_path');
        });

        Schema::table('xxx_employees', function (Blueprint $table) {
            // Restore the original string column
            $table->string('image_path', 255)->nullable()->after('user_status');
        });
    }
};
