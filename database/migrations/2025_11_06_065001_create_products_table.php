<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the products table for glass products (simple and combined)
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 50)->nullable()->unique(); // Optional product code/SKU
            $table->decimal('base_price', 10, 2); // Base unit price
            $table->enum('unit_of_measure', ['m2', 'db', 'm'])->default('m2'); // m², darab, meter
            $table->boolean('is_combined')->default(false); // Is this a combined product?
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes(); // Keep historical quotes valid even if product deleted
            
            // Indexes for search and filtering
            $table->index('name');
            $table->index('code');
            $table->index('is_active');
            $table->index('is_combined');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

