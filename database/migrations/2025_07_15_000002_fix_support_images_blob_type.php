<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if we're using Oracle or MySQL and adjust accordingly
        $connection = DB::connection()->getDriverName();

        if ($connection === 'oracle') {
            // For Oracle, we need to drop and recreate the column
            try {
                Schema::table('support_images', function (Blueprint $table) {
                    $table->dropColumn('image');
                });

                Schema::table('support_images', function (Blueprint $table) {
                    $table->binary('image')->nullable();
                });
            } catch (Exception $e) {
                // If column doesn't exist or other error, continue
            }
        } elseif ($connection === 'mysql') {
            // For MySQL, use LONGBLOB type
            DB::statement('ALTER TABLE support_images MODIFY image LONGBLOB');
        } else {
            // For other databases, try to use binary type
            Schema::table('support_images', function (Blueprint $table) {
                $table->binary('image')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to longText
        Schema::table('support_images', function (Blueprint $table) {
            $table->longText('image')->change();
        });
    }
};
