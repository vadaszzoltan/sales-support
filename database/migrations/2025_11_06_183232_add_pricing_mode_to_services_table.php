<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds pricing_mode column to services table.
     * This defines how the service price should be calculated:
     * - 'per_sqm': Price per square meter (based on product area)
     * - 'per_lm': Price per linear meter (based on product perimeter)
     * - 'per_piece': Price per piece (based on quantity)
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->enum('pricing_mode', ['per_sqm', 'per_lm', 'per_piece'])
                  ->default('per_sqm')
                  ->after('unit_of_measure')
                  ->comment('Pricing calculation mode: per_sqm (per m²), per_lm (per linear meter), per_piece (per piece)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('pricing_mode');
        });
    }
};
