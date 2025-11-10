<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductServicePricing extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'product_service_pricing';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'product_id',
        'service_id',
        'price_per_unit',
        'unit_type', // 'piece', 'sqm', or 'lm'
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'price_per_unit' => 'decimal:2',
        ];
    }

    /**
     * A pricing entry belongs to a product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * A pricing entry belongs to a service
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}

