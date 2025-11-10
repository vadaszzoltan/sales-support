<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAccessoryCompatibility extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'product_accessory_compatibility';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'product_id',
        'accessory_id',
    ];

    /**
     * A compatibility entry belongs to a product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * A compatibility entry belongs to an accessory
     */
    public function accessory()
    {
        return $this->belongsTo(Accessory::class);
    }
}

