<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores global system-wide settings
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique(); // e.g., 'delivery_fee_per_km', 'default_vat_rate'
            $table->text('value')->nullable(); // JSON or string value
            $table->enum('type', ['string', 'decimal', 'integer', 'boolean', 'json'])
                  ->default('string');
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Index for quick lookups
            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};

