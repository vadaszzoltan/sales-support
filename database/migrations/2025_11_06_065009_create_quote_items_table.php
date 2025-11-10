<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the quote_items table for individual line items in a quote
     */
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')
                  ->constrained('quotes')
                  ->onDelete('cascade'); // If quote deleted, delete items
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->onDelete('restrict'); // Don't delete if product used in quotes
            $table->decimal('quantity', 8, 2);
            $table->integer('width_mm')->nullable(); // Width in millimeters
            $table->integer('height_mm')->nullable(); // Height in millimeters
            $table->decimal('surface_area_m2', 8, 4)->nullable(); // Calculated: (width * height) / 1,000,000 * quantity
            $table->decimal('unit_price', 10, 2); // Base product price
            $table->decimal('product_total', 10, 2); // Product price * quantity
            $table->decimal('service_total', 10, 2)->default(0); // Sum of all services
            $table->decimal('accessory_total', 10, 2)->default(0); // Sum of all accessories
            $table->decimal('line_total', 10, 2); // Total for this line
            $table->enum('discount_type', ['none', 'fixed', 'percentage'])->default('none');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0); // For ordering items
            $table->timestamps();
            
            // Indexes for performance
            $table->index('quote_id');
            $table->index('product_id');
            $table->index('sort_order');
            
            // Composite index for ordering items within a quote
            $table->index(['quote_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};

