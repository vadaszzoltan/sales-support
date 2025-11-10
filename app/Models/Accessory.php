<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Accessory extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'code',
        'uniform_price',
        'unit_of_measure',
        'is_active',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'uniform_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * An accessory can be compatible with many products (through compatibility table)
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_accessory_compatibility')
                    ->withTimestamps();
    }

    /**
     * An accessory can be used in multiple quote items (through pivot table)
     */
    public function quoteItems()
    {
        return $this->belongsToMany(QuoteItem::class, 'quote_item_accessory')
                    ->withPivot('quantity', 'unit_price', 'total')
                    ->withTimestamps();
    }

    /**
     * Scope to get only active accessories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

