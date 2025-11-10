<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds translation columns to products table for multi-language support.
     * 
     * Strategy:
     * 1. Add new columns (name_en, name_ro, name_hu, description_en, description_ro, description_hu)
     * 2. Copy existing 'name' and 'description' data to new columns
     * 3. Drop old columns
     * 4. Add indexes
     * 
     * This approach works better with MySQL and avoids renameColumn issues.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Add new name columns with translations
            $table->string('name_en', 255)->nullable()->after('id');
            $table->string('name_ro', 255)->nullable()->after('name_en');
            $table->string('name_hu', 255)->nullable()->after('name_ro');
            
            // Add new description columns with translations
            $table->text('description_en')->nullable()->after('is_active');
            $table->text('description_ro')->nullable()->after('description_en');
            $table->text('description_hu')->nullable()->after('description_ro');
        });
        
        // Migrate existing data: copy 'name' to name_en, name_ro, name_hu
        // and 'description' to description_en, description_ro, description_hu
        DB::statement("
            UPDATE products 
            SET 
                name_en = name,
                name_ro = name,
                name_hu = name,
                description_en = description,
                description_ro = description,
                description_hu = description
            WHERE name IS NOT NULL
        ");
        
        // Make name_en required (not nullable) and drop old columns
        Schema::table('products', function (Blueprint $table) {
            // Make name_en required
            $table->string('name_en', 255)->nullable(false)->change();
            
            // Drop old columns
            $table->dropColumn('name');
            $table->dropColumn('description');
            
            // Add indexes for new name columns
            $table->index('name_en');
            $table->index('name_ro');
            $table->index('name_hu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Recreate original columns
            $table->string('name', 255)->after('id');
            $table->text('description')->nullable()->after('is_active');
        });
        
        // Migrate data back: copy name_en to name, description_en to description
        DB::statement("
            UPDATE products 
            SET 
                name = name_en,
                description = description_en
            WHERE name_en IS NOT NULL
        ");
        
        // Make name required and drop translation columns
        Schema::table('products', function (Blueprint $table) {
            $table->string('name', 255)->nullable(false)->change();
            
            // Drop translation columns
            $table->dropColumn(['name_en', 'name_ro', 'name_hu']);
            $table->dropColumn(['description_en', 'description_ro', 'description_hu']);
        });
    }
};
