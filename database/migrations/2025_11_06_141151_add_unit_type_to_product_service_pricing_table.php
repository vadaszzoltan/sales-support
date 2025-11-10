<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds unit_type column to product_service_pricing table.
     * This defines how the service price is calculated:
     * - 'piece': Price per piece (e.g., Kivágás, Fúrás)
     * - 'sqm': Price per square meter (e.g., Üveg, Edzés, Fólia nyomtatás)
     * - 'lm': Price per linear meter (e.g., Csiszolás)
     */
    public function up(): void
    {
        Schema::table('product_service_pricing', function (Blueprint $table) {
            $table->enum('unit_type', ['piece', 'sqm', 'lm'])
                  ->default('sqm')
                  ->after('price_per_unit')
                  ->comment('Unit type: piece (per piece), sqm (per m²), lm (per linear meter)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_service_pricing', function (Blueprint $table) {
            $table->dropColumn('unit_type');
        });
    }
};
