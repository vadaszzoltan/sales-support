<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Defines which accessories can be linked to which products
     */
    public function up(): void
    {
        Schema::create('product_accessory_compatibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->onDelete('cascade');
            $table->foreignId('accessory_id')
                  ->constrained('accessories')
                  ->onDelete('cascade');
            $table->timestamps();
            
            // Ensure one product-accessory combination is unique
            $table->unique(['product_id', 'accessory_id']);
            
            // Indexes for performance
            $table->index('product_id');
            $table->index('accessory_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_accessory_compatibility');
    }
};

