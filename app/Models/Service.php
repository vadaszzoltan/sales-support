<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Pricing mode constants
     */
    const PRICING_PER_SQM = 'per_sqm';
    const PRICING_PER_LM = 'per_lm';
    const PRICING_PER_PIECE = 'per_piece';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'code',
        'unit_of_measure',
        'pricing_mode',
        'is_active',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * A service can be compatible with many products (through compatibility table)
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_service_compatibility')
                    ->withTimestamps();
    }

    /**
     * A service has many product-specific pricing rules
     */
    public function productPricing()
    {
        return $this->hasMany(ProductServicePricing::class);
    }

    /**
     * A service can be used in multiple quote items (through pivot table)
     */
    public function quoteItems()
    {
        return $this->belongsToMany(QuoteItem::class, 'quote_item_service')
                    ->withPivot('price_per_unit', 'quantity', 'total')
                    ->withTimestamps();
    }

    /**
     * Scope to get only active services
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

