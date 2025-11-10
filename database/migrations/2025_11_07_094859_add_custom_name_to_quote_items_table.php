<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds custom_name column to quote_items table.
     * This allows users to override the product name with a custom display name per quote item.
     */
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('custom_name')->nullable()->after('product_id')
                  ->comment('Custom display name for this quote item. If set, this will be used instead of the product name in quotes and PDFs.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn('custom_name');
        });
    }
};
