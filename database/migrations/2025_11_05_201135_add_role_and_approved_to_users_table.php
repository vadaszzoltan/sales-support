<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add role column: admin or sales_agent
            $table->enum('role', ['admin', 'sales_agent'])
                  ->default('sales_agent')
                  ->after('email');
            
            // Add approved column: sales agents need admin approval
            $table->boolean('approved')
                  ->default(false)
                  ->after('role');
            
            // Add indexes for performance
            $table->index('role');
            $table->index('approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['approved']);
            $table->dropColumn(['role', 'approved']);
        });
    }
};
