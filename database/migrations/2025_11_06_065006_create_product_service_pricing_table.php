<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores product-specific pricing for services
     * (e.g., Polishing on Float 4 = 1.7€, on Float 8 = 2.8€)
     */
    public function up(): void
    {
        Schema::create('product_service_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->onDelete('cascade');
            $table->foreignId('service_id')
                  ->constrained('services')
                  ->onDelete('cascade');
            $table->decimal('price_per_unit', 10, 2); // Price per unit of measure
            $table->timestamps();
            
            // Ensure one product-service pricing combination is unique
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
        Schema::dropIfExists('product_service_pricing');
    }
};

