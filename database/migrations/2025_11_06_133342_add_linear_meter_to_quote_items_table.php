<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds linear_meter column to quote_items table.
     * Linear meter represents the perimeter of the glass piece in meters.
     * Formula: 2 × (width_mm + height_mm) / 1000 × quantity
     */
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->decimal('linear_meter', 8, 2)->nullable()->after('surface_area_m2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn('linear_meter');
        });
    }
};
