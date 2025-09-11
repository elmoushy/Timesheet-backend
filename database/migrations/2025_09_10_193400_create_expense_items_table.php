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
        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_id');
            $table->date('date');
            $table->enum('type', ['meal', 'taxi', 'hotel', 'other']);
            $table->enum('currency', ['USD', 'EUR', 'EGP', 'SAR', 'AED']);
            $table->decimal('amount', 10, 2);
            $table->decimal('currency_rate', 8, 4);
            $table->text('description');
            $table->binary('attachment_blob')->nullable();
            $table->string('attachment_filename')->nullable();
            $table->string('attachment_mime_type')->nullable();
            $table->integer('attachment_size')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('expense_id')->references('id')->on('expenses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};
