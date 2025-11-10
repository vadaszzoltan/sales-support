<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pivot table for many-to-many relationship between QuoteItem and Accessory
     */
    public function up(): void
    {
        Schema::create('quote_item_accessory', function (Blueprint $table) {
            $table->foreignId('quote_item_id')
                  ->constrained('quote_items')
                  ->onDelete('cascade');
            $table->foreignId('accessory_id')
                  ->constrained('accessories')
                  ->onDelete('restrict');
            $table->decimal('quantity', 8, 2);
            $table->decimal('unit_price', 10, 2); // Uniform price at time of quote
            $table->decimal('total', 10, 2); // Calculated: unit_price * quantity
            $table->timestamps();
            
            // Composite primary key
            $table->primary(['quote_item_id', 'accessory_id']);
            
            // Indexes for performance
            $table->index('quote_item_id');
            $table->index('accessory_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_item_accessory');
    }
};

