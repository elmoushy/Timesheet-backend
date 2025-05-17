<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xxx_clients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 120)->unique();
            $table->string('alias', 60)->unique();
            $table->string('region', 60);
            $table->string('address', 255);
            $table->string('business_sector', 120);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_clients');
    }
};
