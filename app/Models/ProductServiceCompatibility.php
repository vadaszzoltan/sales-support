<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductServiceCompatibility extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'product_service_compatibility';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'product_id',
        'service_id',
    ];

    /**
     * A compatibility entry belongs to a product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * A compatibility entry belongs to a service
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}

