<?php

namespace App\Services;

use App\Models\ProductServicePricing;
use App\Models\QuoteItem;
use App\Models\Service;

/**
 * Service class for calculating service prices for quote items.
 * 
 * This handles the business logic for different service types:
 * - Piece-based services (Kivágás, Fúrás): quantity × unit_price
 * - m²-based services (Üveg, Edzés, Fólia nyomtatás, Folie Sablat, Vopsit): surface_area × unit_price
 * - Linear meter-based services (Csiszolás): linear_meter × unit_price × quantity
 */
class ServiceCalculationService
{
    /**
     * Calculate the total price for a service on a quote item.
     * 
     * This method implements the business rules for each service type:
     * 
     * 1. If service is disabled (enabled = false), return 0
     * 2. Get unit_type from product-service pricing (piece, sqm, lm)
     * 3. Calculate based on unit_type:
     *    - 'piece': quantity × unit_price
     *    - 'sqm': surface_area_m2 × unit_price
     *    - 'lm': linear_meter × unit_price × item_quantity
     * 
     * @param QuoteItem $quoteItem The quote item
     * @param Service $service The service
     * @param bool $enabled Whether the service is enabled
     * @param float|null $quantity Override quantity (for piece-based or m²-based services that allow override)
     * @param float|null $unitPrice Override unit price (optional, uses product-service pricing if not provided)
     * @return float Total price for this service (rounded to 2 decimals)
     */
    public function calculateServiceTotal(
        QuoteItem $quoteItem,
        Service $service,
        bool $enabled = true,
        ?float $quantity = null,
        ?float $unitPrice = null
    ): float {
        // If service is disabled, cost is 0
        if (!$enabled) {
            return 0.0;
        }

        // Get product-service pricing
        $pricing = ProductServicePricing::where('product_id', $quoteItem->product_id)
            ->where('service_id', $service->id)
            ->first();

        // If no pricing found, return 0
        if (!$pricing) {
            return 0.0;
        }

        // Use provided unit price or get from pricing
        $pricePerUnit = $unitPrice ?? (float) $pricing->price_per_unit;
        
        // Get unit type from Service's pricing_mode (primary source)
        // Fallback to unit_type from pricing table for backward compatibility
        $pricingMode = $service->pricing_mode ?? null;
        $unitType = null;
        
        if ($pricingMode) {
            // Map pricing_mode to unit_type
            $unitType = match($pricingMode) {
                Service::PRICING_PER_SQM => 'sqm',
                Service::PRICING_PER_LM => 'lm',
                Service::PRICING_PER_PIECE => 'piece',
                default => null,
            };
        }
        
        // Fallback to unit_type from pricing table if pricing_mode is not set
        if (!$unitType) {
            $unitType = $pricing->unit_type ?? 'sqm';
        }

        // Calculate based on unit type
        switch ($unitType) {
            case 'piece':
                // Piece-based services: quantity × unit_price
                // If quantity is provided, use it; otherwise default to 1
                $serviceQuantity = $quantity ?? 1.0;
                return round($serviceQuantity * $pricePerUnit, 2);

            case 'sqm':
                // m²-based services: surface_area_m2 × unit_price
                // For services like Fólia nyomtatás, quantity can override the default (surface_area)
                if ($quantity !== null) {
                    // User has overridden quantity (e.g., for Fólia nyomtatás)
                    return round($quantity * $pricePerUnit, 2);
                }
                // Default: use surface area
                $surfaceArea = (float) ($quoteItem->surface_area_m2 ?? 0);
                return round($surfaceArea * $pricePerUnit, 2);

            case 'lm':
                // Linear meter-based services: linear_meter × unit_price × item_quantity
                // Note: linear_meter is already calculated as: 2 × (width + height) / 1000 × quantity
                // So we multiply by unit_price only (not by quantity again)
                $linearMeter = (float) ($quoteItem->linear_meter ?? 0);
                return round($linearMeter * $pricePerUnit, 2);

            default:
                // Fallback: treat as sqm
                $surfaceArea = (float) ($quoteItem->surface_area_m2 ?? 0);
                return round($surfaceArea * $pricePerUnit, 2);
        }
    }

    /**
     * Get the default quantity for a service based on its pricing mode.
     * 
     * This helps set sensible defaults when a service is enabled:
     * - 'piece': default to 1
     * - 'sqm': default to surface_area_m2
     * - 'lm': not applicable (calculated automatically from dimensions)
     * 
     * @param QuoteItem $quoteItem The quote item
     * @param Service $service The service
     * @return float|null Default quantity, or null if not applicable
     */
    public function getDefaultQuantity(QuoteItem $quoteItem, Service $service): ?float
    {
        // Get unit type from Service's pricing_mode (primary source)
        $pricingMode = $service->pricing_mode ?? null;
        $unitType = null;
        
        if ($pricingMode) {
            // Map pricing_mode to unit_type
            $unitType = match($pricingMode) {
                Service::PRICING_PER_SQM => 'sqm',
                Service::PRICING_PER_LM => 'lm',
                Service::PRICING_PER_PIECE => 'piece',
                default => null,
            };
        }
        
        // Fallback to unit_type from pricing table if pricing_mode is not set
        if (!$unitType) {
            $pricing = ProductServicePricing::where('product_id', $quoteItem->product_id)
                ->where('service_id', $service->id)
                ->first();

            if (!$pricing) {
                return null;
            }

            $unitType = $pricing->unit_type ?? 'sqm';
        }

        switch ($unitType) {
            case 'piece':
                return 1.0;

            case 'sqm':
                return (float) ($quoteItem->surface_area_m2 ?? 0);

            case 'lm':
                // Linear meter is calculated automatically, no default quantity needed
                return null;

            default:
                return (float) ($quoteItem->surface_area_m2 ?? 0);
        }
    }

    /**
     * Check if a service is toggleable (can be enabled/disabled).
     * 
     * Based on business rules, these services are toggleable:
     * - Fólia nyomtatás
     * - Üveg
     * - Csiszolás
     * - Edzés
     * - Folie Sablat (optional)
     * - Vopsit (optional)
     * 
     * Piece-based services (Kivágás, Fúrás) are always enabled but quantity can be 0.
     * 
     * @param Service $service The service
     * @return bool True if service can be toggled ON/OFF
     */
    public function isToggleable(Service $service): bool
    {
        // Check by pricing_mode: piece-based services are not toggleable
        // Others (sqm, lm) are toggleable
        $pricingMode = $service->pricing_mode ?? null;
        
        if ($pricingMode) {
            // Piece-based services are not toggleable (quantity can be 0 instead)
            return $pricingMode !== Service::PRICING_PER_PIECE;
        }
        
        // Fallback: check by unit_type from pricing table
        $pricing = ProductServicePricing::where('service_id', $service->id)->first();
        
        if (!$pricing) {
            return true; // Default to toggleable
        }

        // Piece-based services are not toggleable (quantity can be 0 instead)
        return $pricing->unit_type !== 'piece';
    }

    /**
     * Get all compatible services for a product with their pricing information.
     * 
     * @param int $productId The product ID
     * @return \Illuminate\Database\Eloquent\Collection Collection of services with pricing
     */
    public function getCompatibleServicesWithPricing(int $productId)
    {
        return Service::whereHas('productPricing', function ($query) use ($productId) {
            $query->where('product_id', $productId);
        })
        ->with(['productPricing' => function ($query) use ($productId) {
            $query->where('product_id', $productId);
        }])
        ->active()
        ->get();
    }
}

