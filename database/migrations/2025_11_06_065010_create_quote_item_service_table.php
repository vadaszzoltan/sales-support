<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pivot table for many-to-many relationship between QuoteItem and Service
     * Stores pricing data at time of quote
     */
    public function up(): void
    {
        Schema::create('quote_item_service', function (Blueprint $table) {
            $table->foreignId('quote_item_id')
                  ->constrained('quote_items')
                  ->onDelete('cascade'); // If quote item deleted, delete pivot records
            $table->foreignId('service_id')
                  ->constrained('services')
                  ->onDelete('restrict'); // Don't delete if service used
            $table->decimal('price_per_unit', 10, 2); // Price at time of quote
            $table->decimal('quantity', 8, 2); // Usually same as surface area or item quantity
            $table->decimal('total', 10, 2); // Calculated: price_per_unit * quantity
            $table->timestamps();
            
            // Composite primary key
            // This enforces uniqueness: a service cannot be added twice to the same quote item
            $table->primary(['quote_item_id', 'service_id']);
            
            // Indexes for performance
            $table->index('quote_item_id');
            $table->index('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_item_service');
    }
};

