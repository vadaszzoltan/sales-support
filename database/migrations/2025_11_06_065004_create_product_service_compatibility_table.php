<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Defines which services can be linked to which products
     */
    public function up(): void
    {
        Schema::create('product_service_compatibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->onDelete('cascade'); // If product deleted, remove compatibility
            $table->foreignId('service_id')
                  ->constrained('services')
                  ->onDelete('cascade'); // If service deleted, remove compatibility
            $table->timestamps();
            
            // Ensure one product-service combination is unique
            $table->unique(['product_id', 'service_id']);
            
            // Indexes for performance
            $table->index('product_id');
            $table->index('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_service_compatibility');
    }
};

