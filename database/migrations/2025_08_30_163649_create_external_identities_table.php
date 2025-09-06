<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_identities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 50); // 'entra', 'azure', etc.
            $table->string('external_id', 255); // oid from Microsoft
            $table->string('tenant_id', 255)->nullable(); // Azure tenant ID
            $table->string('external_email', 255)->nullable(); // Email from provider
            $table->string('external_name', 255)->nullable(); // Name from provider
            $table->json('provider_data')->nullable(); // Store raw claims/profile data
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            // Foreign key to employees table
            $table->foreign('user_id')
                ->references('id')
                ->on('xxx_employees')
                ->onDelete('cascade');

            // Unique constraint: one external identity per provider per user
            $table->unique(['provider', 'external_id'], 'uk_provider_external_id');

            // Index for faster lookups
            $table->index(['provider', 'external_id', 'tenant_id'], 'idx_external_lookup');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_identities');
    }
};
