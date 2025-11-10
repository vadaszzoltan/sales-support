<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name_en',
        'name_ro',
        'name_hu',
        'code',
        'base_price',
        'unit_of_measure',
        'is_combined',
        'is_active',
        'description_en',
        'description_ro',
        'description_hu',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_combined' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * A product can be used in multiple quote items
     */
    public function quoteItems()
    {
        return $this->hasMany(QuoteItem::class);
    }

    /**
     * A product can have many compatible services (through compatibility table)
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'product_service_compatibility')
                    ->withTimestamps();
    }

    /**
     * A product can have many compatible accessories (through compatibility table)
     */
    public function accessories()
    {
        return $this->belongsToMany(Accessory::class, 'product_accessory_compatibility')
                    ->withTimestamps();
    }

    /**
     * A product has many product-service pricing rules
     */
    public function servicePricing()
    {
        return $this->hasMany(ProductServicePricing::class);
    }

    /**
     * Scope to get only active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the product name in the specified locale.
     * Falls back to default locale (English) if translation is missing.
     * 
     * @param string|null $locale The locale code (en, ro, hu). If null, uses current app locale
     * @return string The product name in the requested locale
     */
    public function getName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale() ?? config('locales.default', 'en');
        
        // Validate locale
        $supportedLocales = config('locales.supported', ['en', 'ro', 'hu']);
        if (!in_array($locale, $supportedLocales)) {
            $locale = config('locales.default', 'en');
        }
        
        // Try to get name for requested locale
        $column = "name_{$locale}";
        $name = $this->$column;
        
        // If not found, try fallback chain: requested locale -> default locale -> en
        if (empty($name)) {
            $defaultLocale = config('locales.default', 'en');
            
            // Try default locale
            if ($locale !== $defaultLocale) {
                $defaultColumn = "name_{$defaultLocale}";
                $name = $this->$defaultColumn;
            }
            
            // If still not found, try English as final fallback
            if (empty($name) && $locale !== 'en' && $defaultLocale !== 'en') {
                $name = $this->name_en;
            }
        }
        
        // Return name or fallback to English (which should always exist)
        return $name ?: $this->name_en ?: 'N/A';
    }

    /**
     * Get the product description in the specified locale.
     * Falls back to default locale (English) if translation is missing.
     * 
     * @param string|null $locale The locale code (en, ro, hu). If null, uses current app locale
     * @return string|null The product description in the requested locale, or null if not found
     */
    public function getDescription(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale() ?? config('locales.default', 'en');
        
        // Validate locale
        $supportedLocales = config('locales.supported', ['en', 'ro', 'hu']);
        if (!in_array($locale, $supportedLocales)) {
            $locale = config('locales.default', 'en');
        }
        
        // Try to get description for requested locale
        $column = "description_{$locale}";
        $description = $this->$column;
        
        // If not found, try fallback chain: requested locale -> default locale -> en
        if (empty($description)) {
            $defaultLocale = config('locales.default', 'en');
            
            // Try default locale
            if ($locale !== $defaultLocale) {
                $defaultColumn = "description_{$defaultLocale}";
                $description = $this->$defaultColumn;
            }
            
            // If still not found, try English as final fallback
            if (empty($description) && $locale !== 'en' && $defaultLocale !== 'en') {
                $description = $this->description_en;
            }
        }
        
        return $description;
    }
    
    /**
     * Laravel accessor for 'name' attribute (for backward compatibility).
     * This allows existing code using $product->name to still work.
     * It returns the name in the current app locale.
     * 
     * @return string The product name in current locale
     */
    public function getNameAttribute(): string
    {
        // Call getName() without locale parameter to use current app locale
        return $this->getName();
    }
    
    /**
     * Laravel mutator for 'name' attribute (for backward compatibility).
     * This sets the name in the default locale (English).
     * 
     * @param string $value The product name
     * @return void
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name_en'] = $value;
    }
    
    /**
     * Laravel accessor for 'description' attribute (for backward compatibility).
     * This allows existing code using $product->description to still work.
     * It returns the description in the current app locale.
     * 
     * @return string|null The product description in current locale
     */
    public function getDescriptionAttribute(): ?string
    {
        // Call getDescription() without locale parameter to use current app locale
        return $this->getDescription();
    }
    
    /**
     * Laravel mutator for 'description' attribute (for backward compatibility).
     * This sets the description in the default locale (English).
     * 
     * @param string|null $value The product description
     * @return void
     */
    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description_en'] = $value;
    }
}

