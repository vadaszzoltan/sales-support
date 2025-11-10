<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Updates the surface_area_m2 column precision from decimal(8,4) to decimal(8,2)
     * to match the requirement of 2 decimal places for area calculations.
     */
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            // Change precision from (8,4) to (8,2) for surface_area_m2
            $table->decimal('surface_area_m2', 8, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            // Revert back to (8,4) if needed
            $table->decimal('surface_area_m2', 8, 4)->nullable()->change();
        });
    }
};
