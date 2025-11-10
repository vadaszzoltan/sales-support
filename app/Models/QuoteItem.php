<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'quote_id',
        'product_id',
        'custom_name', // Custom display name to override product name
        'quantity',
        'width_mm',
        'height_mm',
        'surface_area_m2',
        'linear_meter',
        'unit_price',
        'product_total',
        'service_total',
        'accessory_total',
        'line_total',
        'discount_type',
        'discount_value',
        'notes',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'surface_area_m2' => 'decimal:2', // Changed from decimal:4 to decimal:2 per requirements
            'linear_meter' => 'decimal:2', // Linear meter in meters (perimeter × quantity)
            'unit_price' => 'decimal:2',
            'product_total' => 'decimal:2',
            'service_total' => 'decimal:2',
            'accessory_total' => 'decimal:2',
            'line_total' => 'decimal:2',
            'discount_value' => 'decimal:2',
        ];
    }

    /**
     * A quote item belongs to a quote
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * A quote item belongs to a product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * A quote item can have many services (through pivot table)
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'quote_item_service')
                    ->withPivot('enabled', 'price_per_unit', 'quantity', 'total')
                    ->withTimestamps();
    }

    /**
     * A quote item can have many accessories (through pivot table)
     */
    public function accessories()
    {
        return $this->belongsToMany(Accessory::class, 'quote_item_accessory')
                    ->withPivot('quantity', 'unit_price', 'total')
                    ->withTimestamps();
    }

    /**
     * Calculate surface area in m² per unit from width and height
     * 
     * Formula: (width_mm * height_mm) / 1,000,000
     * This returns the area for ONE unit, not multiplied by quantity.
     * 
     * @return float|null Area in m² per unit, or null if dimensions are missing
     */
    public function calculateSurfaceAreaPerUnit(): ?float
    {
        if (!$this->width_mm || !$this->height_mm) {
            return null;
        }

        // Convert mm² to m²: (width * height) / 1,000,000
        // This is the area for ONE unit
        return ($this->width_mm * $this->height_mm) / 1000000;
    }

    /**
     * Calculate total surface area in m² (area per unit × quantity)
     * 
     * @return float|null Total area in m², or null if dimensions are missing
     */
    public function calculateTotalSurfaceArea(): ?float
    {
        $areaPerUnit = $this->calculateSurfaceAreaPerUnit();
        if ($areaPerUnit === null) {
            return null;
        }
        
        return $areaPerUnit * (float) $this->quantity;
    }

    /**
     * Calculate linear meter (lm) per unit from width and height
     * 
     * Linear meter represents the perimeter of the glass piece.
     * Formula: 2 × (width_mm + height_mm) / 1000
     * This returns the perimeter for ONE unit in meters, not multiplied by quantity.
     * 
     * @return float|null Linear meter per unit in meters, or null if dimensions are missing
     */
    public function calculateLinearMeterPerUnit(): ?float
    {
        if (!$this->width_mm || !$this->height_mm) {
            return null;
        }

        // Calculate perimeter: 2 × (width + height)
        // Convert from mm to meters: divide by 1000
        // This is the perimeter for ONE unit
        return (2 * ((float) $this->width_mm + (float) $this->height_mm)) / 1000;
    }

    /**
     * Calculate total linear meter (lm) (perimeter per unit × quantity)
     * 
     * This is the total perimeter length for all units combined.
     * 
     * @return float|null Total linear meter in meters, or null if dimensions are missing
     */
    public function calculateTotalLinearMeter(): ?float
    {
        $linearMeterPerUnit = $this->calculateLinearMeterPerUnit();
        if ($linearMeterPerUnit === null) {
            return null;
        }
        
        return $linearMeterPerUnit * (float) $this->quantity;
    }

    /**
     * Calculate product total price based on area-based pricing
     * 
     * This is the SINGLE SOURCE OF TRUTH for product pricing calculation.
     * 
     * Formula:
     * 1. Calculate area per unit: (width_mm × height_mm) / 1,000,000
     * 2. Calculate product total: area_per_unit (m²) × unit_price (€/m²) × quantity
     * 
     * If width or height is missing/zero, we use a fallback:
     * - Fallback: unit_price × quantity (treats as if area = 1.0 m² per unit)
     * 
     * This ensures backward compatibility with existing quotes that may not have dimensions.
     * 
     * @return float Product total in € (rounded to 2 decimals)
     */
    public function calculateProductTotal(): float
    {
        $areaPerUnit = $this->calculateSurfaceAreaPerUnit();
        $quantity = (float) $this->quantity;
        $unitPrice = (float) $this->unit_price;

        if ($areaPerUnit !== null && $areaPerUnit > 0) {
            // Area-based pricing: area (m²) × price (€/m²) × quantity
            $productTotal = $areaPerUnit * $unitPrice * $quantity;
        } else {
            // Fallback: no dimensions or zero area, use simple calculation
            // This treats it as if each unit has 1.0 m² area
            // This is a safe default that maintains backward compatibility
            $productTotal = $unitPrice * $quantity;
        }

        return round($productTotal, 2);
    }

    /**
     * Calculate the complete line total including product, services, accessories, and discounts
     * 
     * This method calculates:
     * 1. Product total (using area-based pricing)
     * 2. Service total (sum of all services)
     * 3. Accessory total (sum of all accessories)
     * 4. Line subtotal = product + services + accessories
     * 5. Apply item-level discount
     * 6. Line total = subtotal - discount
     * 
     * @return array Array with all calculated values:
     *   - 'product_total' => float
     *   - 'service_total' => float
     *   - 'accessory_total' => float
     *   - 'line_subtotal' => float
     *   - 'item_discount' => float
     *   - 'line_total' => float
     */
    public function calculateLineTotal(): array
    {
        // Load relationships if not already loaded
        $this->loadMissing(['services', 'accessories']);

        // 1. Calculate product total using area-based pricing
        $productTotal = $this->calculateProductTotal();

        // 2. Calculate service total
        $serviceTotal = 0;
        foreach ($this->services as $service) {
            $pricePerUnit = (float) $service->pivot->price_per_unit;
            $serviceQuantity = (float) $service->pivot->quantity;
            $serviceTotal += $pricePerUnit * $serviceQuantity;
        }

        // 3. Calculate accessory total
        $accessoryTotal = 0;
        foreach ($this->accessories as $accessory) {
            $unitPrice = (float) $accessory->pivot->unit_price;
            $accessoryQuantity = (float) $accessory->pivot->quantity;
            $accessoryTotal += $unitPrice * $accessoryQuantity;
        }

        // 4. Calculate line subtotal
        $lineSubtotal = $productTotal + $serviceTotal + $accessoryTotal;

        // 5. Apply item-level discount
        $itemDiscount = 0;
        if ($this->discount_type === 'fixed') {
            $itemDiscount = (float) $this->discount_value;
        } elseif ($this->discount_type === 'percentage') {
            $itemDiscount = ($lineSubtotal * (float) $this->discount_value) / 100;
        }

        // 6. Calculate final line total
        $lineTotal = $lineSubtotal - $itemDiscount;

        return [
            'product_total' => round($productTotal, 2),
            'service_total' => round($serviceTotal, 2),
            'accessory_total' => round($accessoryTotal, 2),
            'line_subtotal' => round($lineSubtotal, 2),
            'item_discount' => round($itemDiscount, 2),
            'line_total' => round($lineTotal, 2),
        ];
    }

    /**
     * Get the display name for this quote item.
     * Returns custom_name if set, otherwise returns the product name in the specified locale.
     * 
     * @param string|null $locale The locale code (en, ro, hu). If null, uses current app locale
     * @return string The display name to use in quotes and PDFs
     */
    public function getDisplayName(?string $locale = null): string
    {
        // If custom name is set, always use it (user override takes precedence)
        if ($this->custom_name) {
            return $this->custom_name;
        }

        // Otherwise, get product name in the requested locale with fallback
        if ($this->product) {
            return $this->product->getName($locale);
        }

        return 'N/A';
    }
}


