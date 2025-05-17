<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xxx_proj_products', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('product_id');

            $table->primary(['project_id','product_id'], 'pk_proj_prod');

            $table->foreign('project_id', 'fk_pp_proj')
                  ->references('id')->on('xxx_projects')
                  ->onDelete('cascade');

            $table->foreign('product_id', 'fk_pp_prod')
                  ->references('id')->on('xxx_products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xxx_proj_products');
    }
};
