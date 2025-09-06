<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('token', 64)->unique(); // Hashed refresh token
            $table->string('device_id', 255)->nullable(); // Optional device identification
            $table->string('user_agent', 500)->nullable(); // User agent for tracking
            $table->ipAddress('ip_address')->nullable(); // IP address for security
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();

            // Foreign key to employees table
            $table->foreign('user_id')
                ->references('id')
                ->on('xxx_employees')
                ->onDelete('cascade');

            // Indexes for performance
            $table->index('token');
            $table->index(['user_id', 'is_revoked']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
