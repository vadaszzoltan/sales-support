<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds enabled column to quote_item_service pivot table.
     * This allows services to be toggled ON/OFF (e.g., Fólia nyomtatás, Üveg, Csiszolás, Edzés).
     * When enabled = false, the service cost is 0.
     */
    public function up(): void
    {
        Schema::table('quote_item_service', function (Blueprint $table) {
            $table->boolean('enabled')
                  ->default(true)
                  ->after('service_id')
                  ->comment('Whether this service is enabled for this quote item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_item_service', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });
    }
};
