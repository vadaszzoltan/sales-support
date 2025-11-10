<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\SystemSetting;

/**
 * Service class for calculating quote prices and totals.
 * 
 * Implements the pricing logic from the SRS and technical plan:
 * - Product prices
 * - Service prices (product-specific, based on area and quantity)
 * - Accessory prices (uniform)
 * - Installation cost (manpower multiplier)
 * - Delivery cost (distance × €/km)
 * - Discounts (fixed or percentage)
 * - VAT calculation
 * - Grand total
 */
class QuotePricingService
{
    /**
     * Calculate the total for a single quote item
     * 
     * This calculates:
     * - Product total (base_price × quantity)
     * - Service total (sum of all services with product-specific pricing)
     * - Accessory total (sum of all accessories with uniform pricing)
     * - Line total (product + services + accessories - item discount)
     * 
     * @param QuoteItem $item The quote item to calculate
     * @return array Array with calculated totals
     */
    public function calculateItemTotal(QuoteItem $item): array
    {
        // Load relationships if not already loaded
        $item->loadMissing(['product', 'services', 'accessories']);

        // 1. Calculate Product Total using area-based pricing
        // This uses the QuoteItem model's calculateProductTotal() method which is the
        // SINGLE SOURCE OF TRUTH for product pricing calculation.
        // 
        // Formula: area_per_unit (m²) × unit_price (€/m²) × quantity
        // If no dimensions, fallback to: unit_price × quantity
        $productTotal = $item->calculateProductTotal();

        // 2. Calculate Service Total
        // For each service: price_per_unit × service_quantity
        // Service quantity depends on unit of measure:
        // - If m²: service_quantity = surface_area_m2 (or calculate from width × height × quantity)
        // - If db: service_quantity = item quantity
        // - If m: service_quantity = calculated length
        $serviceTotal = 0;
        
        foreach ($item->services as $service) {
            $pricePerUnit = (float) $service->pivot->price_per_unit;
            $serviceQuantity = (float) $service->pivot->quantity;
            $serviceTotal += $pricePerUnit * $serviceQuantity;
        }

        // 3. Calculate Accessory Total
        // For each accessory: uniform_price × quantity
        $accessoryTotal = 0;
        
        foreach ($item->accessories as $accessory) {
            $unitPrice = (float) $accessory->pivot->unit_price;
            $accessoryQuantity = (float) $accessory->pivot->quantity;
            $accessoryTotal += $unitPrice * $accessoryQuantity;
        }

        // 4. Calculate Line Subtotal (before item discount)
        $lineSubtotal = $productTotal + $serviceTotal + $accessoryTotal;

        // 5. Apply Item-Level Discount
        $itemDiscountAmount = 0;
        if ($item->discount_type === 'fixed') {
            $itemDiscountAmount = (float) $item->discount_value;
        } elseif ($item->discount_type === 'percentage') {
            $itemDiscountAmount = ($lineSubtotal * (float) $item->discount_value) / 100;
        }

        // 6. Calculate Line Total (after item discount)
        $lineTotal = $lineSubtotal - $itemDiscountAmount;

        return [
            'product_total' => round($productTotal, 2),
            'service_total' => round($serviceTotal, 2),
            'accessory_total' => round($accessoryTotal, 2),
            'line_subtotal' => round($lineSubtotal, 2),
            'item_discount' => round($itemDiscountAmount, 2),
            'line_total' => round($lineTotal, 2),
        ];
    }

    /**
     * Calculate all totals for a quote
     * 
     * This implements the complete pricing logic from the SRS:
     * 1. Sum all item line totals → Subtotal
     * 2. Calculate installation cost (subtotal × multiplier)
     * 3. Calculate delivery cost (distance × €/km)
     * 4. Apply quote-level discount
     * 5. Calculate VAT
     * 6. Calculate grand total
     * 
     * @param Quote $quote The quote to calculate totals for
     * @return array Array with all calculated totals
     */
    public function calculateQuoteTotal(Quote $quote): array
    {
        // Load relationships if not already loaded
        $quote->loadMissing(['items', 'items.product', 'items.services', 'items.accessories']);

        // 1. Calculate Subtotal (sum of all item line totals)
        $subtotal = 0;
        $itemsData = [];

        foreach ($quote->items as $item) {
            $itemTotals = $this->calculateItemTotal($item);
            
            // Update the item with calculated totals
            $item->product_total = $itemTotals['product_total'];
            $item->service_total = $itemTotals['service_total'];
            $item->accessory_total = $itemTotals['accessory_total'];
            $item->line_total = $itemTotals['line_total'];
            
            $subtotal += $itemTotals['line_total'];
            $itemsData[] = $itemTotals;
        }

        // 2. Calculate Installation Cost (Manopera)
        // installation_cost = (subtotal) × installation_multiplier
        // Multiplier comes from quote override or system settings
        $installationMultiplier = $quote->installation_multiplier_override 
            ?? SystemSetting::getValue('installation_multiplier', 0.15); // Default 15%
        
        $installationCost = $subtotal * (float) $installationMultiplier;

        // 3. Calculate Delivery Cost
        // delivery_cost = delivery_distance_km × delivery_fee_per_km
        $deliveryFeePerKm = SystemSetting::getValue('delivery_fee_per_km', 1.5); // Default 1.5€/km
        $deliveryCost = 0;
        
        if ($quote->delivery_distance_km) {
            $deliveryCost = (float) $quote->delivery_distance_km * (float) $deliveryFeePerKm;
        }

        // 4. Calculate Quote-Level Discount
        $totalDiscount = 0;
        if ($quote->discount_type === 'fixed') {
            $totalDiscount = (float) $quote->discount_value;
        } elseif ($quote->discount_type === 'percentage') {
            $totalDiscount = ($subtotal * (float) $quote->discount_value) / 100;
        }

        // 5. Calculate Net Total (before VAT)
        $netTotal = $subtotal + $installationCost + $deliveryCost - $totalDiscount;

        // 6. Calculate VAT
        // vat_amount = net_total × vat_rate / 100
        $vatRate = $quote->vat_rate ?? SystemSetting::getValue('default_vat_rate', 27); // Default 27%
        $vatAmount = $netTotal * ((float) $vatRate / 100);

        // 7. Calculate Grand Total
        // total_amount = net_total + vat_amount
        $totalAmount = $netTotal + $vatAmount;

        return [
            'subtotal' => round($subtotal, 2),
            'installation_cost' => round($installationCost, 2),
            'delivery_cost' => round($deliveryCost, 2),
            'total_discount' => round($totalDiscount, 2),
            'net_total' => round($netTotal, 2),
            'vat_rate' => (float) $vatRate,
            'vat_amount' => round($vatAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'items_data' => $itemsData,
        ];
    }

    /**
     * Recalculate and save all totals for a quote
     * 
     * This method:
     * 1. Calculates all item totals and updates items
     * 2. Calculates quote totals
     * 3. Saves everything to database
     * 
     * @param Quote $quote The quote to recalculate
     * @return Quote The updated quote
     */
    public function recalculateAndSave(Quote $quote): Quote
    {
        // Calculate quote totals (this also updates item totals)
        $totals = $this->calculateQuoteTotal($quote);

        // Update quote with calculated totals
        $quote->subtotal = $totals['subtotal'];
        $quote->installation_cost = $totals['installation_cost'];
        $quote->delivery_cost = $totals['delivery_cost'];
        $quote->total_discount = $totals['total_discount'];
        $quote->vat_rate = $totals['vat_rate'];
        $quote->vat_amount = $totals['vat_amount'];
        $quote->total_amount = $totals['total_amount'];

        // Save quote
        $quote->save();

        // Save all items with updated totals
        foreach ($quote->items as $item) {
            $item->save();
        }

        return $quote->fresh(['items', 'items.product', 'items.services', 'items.accessories']);
    }

    /**
     * Calculate surface area in m² from width and height
     * 
     * Formula: (width_mm × height_mm) / 1,000,000 × quantity
     * 
     * @param int|null $widthMm Width in millimeters
     * @param int|null $heightMm Height in millimeters
     * @param float $quantity Quantity
     * @return float|null Surface area in m², or null if dimensions not provided
     */
    public function calculateSurfaceArea(?int $widthMm, ?int $heightMm, float $quantity): ?float
    {
        if (!$widthMm || !$heightMm) {
            return null;
        }

        // Convert mm² to m²: (width × height) / 1,000,000
        $areaPerUnit = ($widthMm * $heightMm) / 1000000;
        
        // Multiply by quantity
        return round($areaPerUnit * (float) $quantity, 4);
    }

    /**
     * Get compatible services for a product
     * 
     * Returns services that can be linked to the given product,
     * based on Product_Service_Compatibility table.
     * 
     * @param int $productId The product ID
     * @return \Illuminate\Database\Eloquent\Collection Collection of Service models
     */
    public function getCompatibleServices(int $productId)
    {
        return \App\Models\Product::find($productId)
            ->services()
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get compatible accessories for a product
     * 
     * Returns accessories that can be linked to the given product,
     * based on Product_Accessory_Compatibility table.
     * 
     * @param int $productId The product ID
     * @return \Illuminate\Database\Eloquent\Collection Collection of Accessory models
     */
    public function getCompatibleAccessories(int $productId)
    {
        return \App\Models\Product::find($productId)
            ->accessories()
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get service price for a specific product-service combination
     * 
     * Returns the price_per_unit from Product_Service_Pricing table.
     * 
     * @param int $productId The product ID
     * @param int $serviceId The service ID
     * @return float|null Price per unit, or null if not found
     */
    public function getServicePriceForProduct(int $productId, int $serviceId): ?float
    {
        $pricing = \App\Models\ProductServicePricing::where('product_id', $productId)
            ->where('service_id', $serviceId)
            ->first();

        return $pricing ? (float) $pricing->price_per_unit : null;
    }
}

