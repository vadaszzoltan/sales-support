<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the ui_texts table for storing translatable UI labels and messages.
     * Supports multiple languages: English (en), Romanian (ro), Hungarian (hu).
     */
    public function up(): void
    {
        Schema::create('ui_texts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->unique(); // Unique key identifier (e.g., 'quote.status.draft')
            $table->text('value_en')->nullable(); // English translation
            $table->text('value_ro')->nullable(); // Romanian translation
            $table->text('value_hu')->nullable(); // Hungarian translation
            $table->text('description')->nullable(); // Description of what this text is used for
            $table->timestamps();
            
            // Indexes for performance
            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ui_texts');
    }
};
