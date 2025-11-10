<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the quotes table - main transactional entity with versioning
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number', 50)->unique(); // Sequential: "AJ-2024-00123-V1"
            $table->integer('version')->default(1);
            $table->foreignId('parent_quote_id')
                  ->nullable()
                  ->constrained('quotes')
                  ->onDelete('set null'); // Self-referential for versioning
            $table->foreignId('customer_id')
                  ->constrained('customers')
                  ->onDelete('restrict'); // Don't delete quotes if customer deleted
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('restrict'); // Creator/owner
            $table->string('status', 50)->default('draft'); // From global settings
            $table->date('quote_date');
            $table->date('valid_until')->nullable(); // Optional expiration date
            $table->decimal('delivery_distance_km', 8, 2)->nullable();
            $table->decimal('delivery_cost', 10, 2)->default(0);
            $table->decimal('installation_cost', 10, 2)->default(0); // Manopera
            $table->decimal('installation_multiplier_override', 5, 2)->nullable(); // Override for this quote
            $table->enum('discount_type', ['none', 'fixed', 'percentage'])->default('none');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0); // Before discount
            $table->decimal('total_discount', 10, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(27); // Default from settings, can override
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0); // Grand total
            $table->text('notes')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->string('pdf_path', 500)->nullable(); // Storage path for generated PDF
            $table->timestamps();
            $table->softDeletes(); // Soft delete for historical data
            
            // Indexes for performance
            $table->index('quote_number');
            $table->index('customer_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('quote_date');
            $table->index('parent_quote_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};

