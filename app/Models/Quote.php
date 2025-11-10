<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'quote_number',
        'version',
        'parent_quote_id',
        'customer_id',
        'user_id',
        'status',
        'quote_date',
        'valid_until',
        'delivery_distance_km',
        'delivery_cost',
        'installation_cost',
        'installation_multiplier_override',
        'discount_type',
        'discount_value',
        'subtotal',
        'total_discount',
        'vat_rate',
        'vat_amount',
        'total_amount',
        'notes',
        'pdf_generated_at',
        'pdf_path',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'valid_until' => 'date',
            'delivery_distance_km' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'installation_cost' => 'decimal:2',
            'installation_multiplier_override' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total_discount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'pdf_generated_at' => 'datetime',
        ];
    }

    /**
     * A quote belongs to a customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * A quote belongs to a user (creator)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A quote can have a parent quote (for versioning)
     */
    public function parentQuote()
    {
        return $this->belongsTo(Quote::class, 'parent_quote_id');
    }

    /**
     * A quote can have many versions
     */
    public function versions()
    {
        return $this->hasMany(Quote::class, 'parent_quote_id');
    }

    /**
     * A quote has many items
     */
    public function items()
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    /**
     * Check if this is the latest version
     */
    public function isLatestVersion(): bool
    {
        if (!$this->parent_quote_id) {
            // If no parent, check if there are newer versions
            return !$this->versions()->where('version', '>', $this->version)->exists();
        }
        
        // If has parent, check against parent's versions
        return $this->parentQuote->versions()
            ->where('version', '>', $this->version)
            ->doesntExist();
    }
}

