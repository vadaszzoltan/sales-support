<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\SystemSetting;
use App\Services\QuoteDuplicateService;
use App\Services\QuotePdfService;
use App\Services\QuotePricingService;
use App\Services\ServiceCalculationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class QuoteEditor extends Component
{
    // Quote properties
    public $quoteId = null;
    public $quoteNumber = '';
    public $customerId = null;
    public $status = 'draft';
    public $quoteDate;
    public $validUntil = null;
    public $deliveryDistanceKm = null;
    public $installationMultiplierOverride = null;
    public $discountType = 'none';
    public $discountValue = 0;
    public $vatRate = 27;
    public $notes = '';

    // Calculated totals (read-only, updated by pricing service)
    public $subtotal = 0;
    public $installationCost = 0;
    public $deliveryCost = 0;
    public $totalDiscount = 0;
    public $vatAmount = 0;
    public $totalAmount = 0;

    // Items array - each item is an array with product, quantity, dimensions, services, accessories
    public $items = [];

    // UI state
    public $showCustomerModal = false;
    public $newCustomerName = '';
    public $newCustomerEmail = '';
    public $newCustomerPhone = '';

    /**
     * Mount the component - handle both create and edit modes
     */
    public function mount($quote = null)
    {
        $this->quoteDate = now()->format('Y-m-d');

        if ($quote) {
            // Edit mode - load existing quote
            $this->quoteId = $quote instanceof Quote ? $quote->id : $quote;
            $this->loadQuote();
        } else {
            // Create mode - initialize empty quote
            $this->initializeNewQuote();
        }
    }

    /**
     * Load existing quote data
     */
    protected function loadQuote()
    {
        $quote = Quote::with([
            'items.product',
            'items.services',
            'items.accessories',
            'customer'
        ])->findOrFail($this->quoteId);

        // Check authorization
        $this->authorize('update', $quote);

        // Load quote properties
        $this->quoteNumber = $quote->quote_number;
        $this->customerId = $quote->customer_id;
        $this->status = $quote->status;
        $this->quoteDate = $quote->quote_date->format('Y-m-d');
        $this->validUntil = $quote->valid_until ? $quote->valid_until->format('Y-m-d') : null;
        $this->deliveryDistanceKm = $quote->delivery_distance_km;
        $this->installationMultiplierOverride = $quote->installation_multiplier_override;
        $this->discountType = $quote->discount_type;
        $this->discountValue = $quote->discount_value;
        $this->vatRate = $quote->vat_rate;
        $this->notes = $quote->notes ?? '';

        // Load items from database
        // When editing an existing quote, items already have database IDs
        $this->items = [];
        foreach ($quote->items as $item) {
            // Calculate area per unit for display
            $areaPerUnit = $item->calculateSurfaceAreaPerUnit();
            $calculatedArea = $areaPerUnit ? $areaPerUnit * (float) $item->quantity : null;
            
            // Calculate linear meter per unit for display
            $linearMeterPerUnit = $item->calculateLinearMeterPerUnit();
            $calculatedLinearMeter = $linearMeterPerUnit ? $linearMeterPerUnit * (float) $item->quantity : null;
            
            // Generate a temporary ID for tracking (even existing items get one for consistency)
            $tempId = Str::uuid()->toString();
            
            $this->items[] = [
                'temp_id' => $tempId, // Add temporary ID for tracking
                'id' => $item->id, // Database ID (already exists for saved items)
                'product_id' => $item->product_id,
                'custom_name' => $item->custom_name ?? '', // Load custom display name if set
                'quantity' => $item->quantity,
                'width_mm' => $item->width_mm,
                'height_mm' => $item->height_mm,
                'unit_price' => $item->unit_price,
                'discount_type' => $item->discount_type,
                'discount_value' => $item->discount_value,
                'notes' => $item->notes ?? '',
                'calculated_area' => $calculatedArea, // Store calculated area for display
                'calculated_linear_meter' => $calculatedLinearMeter ? round($calculatedLinearMeter, 2) : null, // Store calculated linear meter for display
                'calculated_line_total' => $item->line_total ?? 0, // Store line total for display
                'selected_services' => $item->services->mapWithKeys(function ($service) use ($item) {
                    // Get unit_type from product-service pricing
                    $pricing = \App\Models\ProductServicePricing::where('product_id', $item->product_id)
                        ->where('service_id', $service->id)
                        ->first();
                    
                    return [$service->id => [
                        'enabled' => $service->pivot->enabled ?? true,
                        'price_per_unit' => $service->pivot->price_per_unit,
                        'quantity' => $service->pivot->quantity,
                        'unit_type' => $pricing->unit_type ?? 'sqm',
                    ]];
                })->toArray(),
                'selected_accessories' => $item->accessories->mapWithKeys(function ($accessory) use ($item) {
                    return [$accessory->id => [
                        'quantity' => $accessory->pivot->quantity,
                    ]];
                })->toArray(),
            ];
        }

        // Calculate totals
        $this->recalculateTotals();
    }

    /**
     * Initialize new quote with default values
     * 
     * This method is called when creating a NEW quote (not editing an existing one).
     * It sets up default values and creates one empty item that the user can start filling in.
     * The user can add more items BEFORE saving the quote - they're stored in memory.
     */
    protected function initializeNewQuote()
    {
        // Get default VAT rate from system settings
        $this->vatRate = SystemSetting::getValue('default_vat_rate', 27);
        
        // Initialize with one empty item
        // Each item gets a unique temporary ID (UUID) so we can track it even if array indices change
        $this->items = [
            $this->createEmptyItem()
        ];
    }

    /**
     * Create a new empty item with a unique temporary ID
     * 
     * This is used when adding new items before the quote is saved.
     * The temporary ID (UUID) helps track items even when array indices change.
     * 
     * @return array Empty item structure with unique temp_id
     */
    protected function createEmptyItem(): array
    {
        return [
            'temp_id' => Str::uuid()->toString(), // Unique temporary ID for tracking
            'id' => null, // Database ID (null until saved)
            'product_id' => null,
            'quantity' => 1,
            'width_mm' => null,
            'height_mm' => null,
            'unit_price' => 0,
            'discount_type' => 'none',
            'discount_value' => 0,
            'notes' => '',
            'calculated_area' => null, // Will be calculated when dimensions are entered
            'calculated_linear_meter' => null, // Will be calculated when dimensions are entered
            'calculated_line_total' => 0, // Will be calculated when product/price is entered
            'selected_services' => [],
            'selected_accessories' => [],
        ];
    }

    /**
     * Add a new empty item to the quote
     */
    public function addItem()
    {
        $this->items[] = [
            'id' => null,
            'product_id' => null,
            'custom_name' => '', // Custom display name (optional)
            'quantity' => 1,
            'width_mm' => null,
            'height_mm' => null,
            'unit_price' => 0,
            'discount_type' => 'none',
            'discount_value' => 0,
            'notes' => '',
            'calculated_area' => null, // Will be calculated when dimensions are entered
            'calculated_line_total' => 0, // Will be calculated when product/price is entered
            'selected_services' => [],
            'selected_accessories' => [],
        ];
        
        // Recalculate totals after adding item (in case other items need updating)
        $this->recalculateTotals();
    }

    /**
     * Remove an item from the quote by its temporary ID
     * 
     * This works for both:
     * - Items that haven't been saved yet (temp_id only)
     * - Items that are already in the database (have both id and temp_id)
     * 
     * @param string $tempId The temporary UUID of the item to remove
     */
    public function removeItem($tempId)
    {
        // Find and remove the item by its temporary ID
        $this->items = array_filter($this->items, function ($item) use ($tempId) {
            return ($item['temp_id'] ?? null) !== $tempId;
        });
        
        // Reindex array to maintain sequential indices
        $this->items = array_values($this->items);
        
        // If no items left, add one empty item (user must have at least one item)
        if (empty($this->items)) {
            $this->addItem();
        }
        
        // Recalculate totals after removing item
        $this->recalculateTotals();
    }

    /**
     * When product is selected for an item, load compatible services and accessories
     * This is called automatically by Livewire when items.* properties change via wire:model.live
     * 
     * IMPORTANT: This hook is called AFTER the property value has been updated in $this->items
     */
    public function updatedItems($value, $path)
    {
        // Parse the path to get item index and field name
        // Format: "items.0.product_id" or "items.0.width_mm", etc.
        $parts = explode('.', $path);
        
        if (count($parts) >= 3) {
            $itemIndex = (int) $parts[1];
            $fieldName = $parts[2];
            
            // When product changes, load its base price and clear services/accessories
            if ($fieldName === 'product_id' && $value) {
                $product = Product::find($value);
                if ($product) {
                    $this->items[$itemIndex]['unit_price'] = $product->base_price;
                    
                    // Clear services and accessories when product changes
                    // Services will be reloaded when user selects them
                    $this->items[$itemIndex]['selected_services'] = [];
                    $this->items[$itemIndex]['selected_accessories'] = [];
                }
            }
        }
        
        // Always recalculate totals when any item data changes
        // The $this->items array is already updated at this point, so we can safely recalculate
        // Use $this->dispatch('$refresh') to force component re-render with new calculated values
        $this->recalculateTotals();
    }
    
    /**
     * Explicit update hooks for critical fields that affect calculations
     * These ensure recalculateTotals() is called even if updatedItems() doesn't fire
     */
    
    public function updated($propertyName)
    {
        // If any item field changes (width, height, quantity, unit_price), recalculate
        if (str_starts_with($propertyName, 'items.')) {
            $this->recalculateTotals();
        }
        
        // Recalculate when quote-level fields change
        if (in_array($propertyName, ['deliveryDistanceKm', 'installationMultiplierOverride', 'discountType', 'discountValue', 'vatRate'])) {
            $this->recalculateTotals();
        }
    }

    /**
     * Get compatible services for a product with pricing information (used in view)
     */
    public function getCompatibleServices($productId)
    {
        if (!$productId) {
            return collect();
        }

        $serviceCalcService = app(ServiceCalculationService::class);
        return $serviceCalcService->getCompatibleServicesWithPricing($productId);
    }

    /**
     * Get compatible accessories for a product (used in view)
     */
    public function getCompatibleAccessories($productId)
    {
        if (!$productId) {
            return collect();
        }

        $pricingService = app(QuotePricingService::class);
        return $pricingService->getCompatibleAccessories($productId);
    }

    /**
     * Get service price for a product-service combination
     */
    public function getServicePrice($productId, $serviceId)
    {
        if (!$productId || !$serviceId) {
            return 0;
        }

        $pricingService = app(QuotePricingService::class);
        return $pricingService->getServicePriceForProduct($productId, $serviceId) ?? 0;
    }

    /**
     * Toggle a service for an item (enable/disable)
     * 
     * For toggleable services (Üveg, Csiszolás, Edzés, etc.), this enables/disables them.
     * For piece-based services (Kivágás, Fúrás), this adds/removes them.
     */
    public function toggleService($itemIndex, $serviceId)
    {
        $productId = $this->items[$itemIndex]['product_id'] ?? null;
        if (!$productId) {
            return; // Can't add service without product
        }

        // Get service and pricing information
        $service = \App\Models\Service::find($serviceId);
        if (!$service) {
            return;
        }

        $pricing = \App\Models\ProductServicePricing::where('product_id', $productId)
            ->where('service_id', $serviceId)
            ->first();

        if (!$pricing) {
            return; // Service not compatible with this product
        }

        $serviceCalcService = app(ServiceCalculationService::class);

        // Prevent duplicate services - each service can only be added once per quote item
        // Since service_id is used as array key, duplicates are automatically prevented
        // But we check explicitly for better user feedback
        if (!isset($this->items[$itemIndex]['selected_services'][$serviceId])) {
            // Service not yet added - add it with default values
            $defaultQuantity = $serviceCalcService->getDefaultQuantity(
                $this->createQuoteItemFromArray($this->items[$itemIndex]),
                $service
            );

            $this->items[$itemIndex]['selected_services'][$serviceId] = [
                'enabled' => true,
                'unit_type' => $pricing->unit_type ?? 'sqm',
                'price_per_unit' => (float) $pricing->price_per_unit,
                'quantity' => $defaultQuantity ?? 1.0,
            ];
            
            // Show success message (optional)
            session()->flash('service_added', "Service '{$service->name}' added successfully.");
        } else {
            // Toggle enabled state for toggleable services, or remove for piece-based
            if ($serviceCalcService->isToggleable($service)) {
                // Toggle enabled state
                $this->items[$itemIndex]['selected_services'][$serviceId]['enabled'] = 
                    !($this->items[$itemIndex]['selected_services'][$serviceId]['enabled'] ?? true);
            } else {
                // Remove piece-based service
                unset($this->items[$itemIndex]['selected_services'][$serviceId]);
            }
        }
        
        $this->recalculateTotals();
    }

    /**
     * Helper method to create a temporary QuoteItem model from array data
     * This is used for service calculations before the item is saved
     */
    protected function createQuoteItemFromArray(array $itemData): QuoteItem
    {
        $item = new QuoteItem();
        $item->product_id = $itemData['product_id'] ?? null;
        $item->quantity = $itemData['quantity'] ?? 1;
        $item->width_mm = $itemData['width_mm'] ?? null;
        $item->height_mm = $itemData['height_mm'] ?? null;
        $item->surface_area_m2 = $itemData['calculated_area'] ?? null;
        $item->linear_meter = $itemData['calculated_linear_meter'] ?? null;
        return $item;
    }

    /**
     * Toggle an accessory for an item
     */
    public function toggleAccessory($itemIndex, $accessoryId)
    {
        if (!isset($this->items[$itemIndex]['selected_accessories'][$accessoryId])) {
            // Add accessory
            $accessory = \App\Models\Accessory::find($accessoryId);
            if ($accessory) {
                $this->items[$itemIndex]['selected_accessories'][$accessoryId] = [
                    'quantity' => 1, // Default quantity
                ];
            }
        } else {
            // Remove accessory
            unset($this->items[$itemIndex]['selected_accessories'][$accessoryId]);
        }
        
        $this->recalculateTotals();
    }

    /**
     * Update service quantity for an item
     * Used for services that allow quantity override (e.g., Fólia nyomtatás, Kivágás, Fúrás)
     */
    public function updateServiceQuantity($itemIndex, $serviceId, $quantity)
    {
        if (isset($this->items[$itemIndex]['selected_services'][$serviceId])) {
            $this->items[$itemIndex]['selected_services'][$serviceId]['quantity'] = (float) $quantity;
            $this->recalculateTotals();
        }
    }

    /**
     * Update service unit price for an item (optional override)
     */
    public function updateServicePrice($itemIndex, $serviceId, $price)
    {
        if (isset($this->items[$itemIndex]['selected_services'][$serviceId])) {
            $this->items[$itemIndex]['selected_services'][$serviceId]['price_per_unit'] = (float) $price;
            $this->recalculateTotals();
        }
    }

    /**
     * Update accessory quantity for an item
     */
    public function updateAccessoryQuantity($itemIndex, $accessoryId, $quantity)
    {
        if (isset($this->items[$itemIndex]['selected_accessories'][$accessoryId])) {
            $this->items[$itemIndex]['selected_accessories'][$accessoryId]['quantity'] = (float) $quantity;
            $this->recalculateTotals();
        }
    }

    /**
     * Calculate service quantity based on item dimensions and service unit of measure
     */
    protected function calculateServiceQuantity($itemIndex): float
    {
        $item = $this->items[$itemIndex];
        $quantity = (float) ($item['quantity'] ?? 1);
        
        // If dimensions are provided, calculate surface area
        if ($item['width_mm'] && $item['height_mm']) {
            $pricingService = app(QuotePricingService::class);
            $surfaceArea = $pricingService->calculateSurfaceArea(
                $item['width_mm'],
                $item['height_mm'],
                $quantity
            );
            
            // For m² services, use surface area; for others, use quantity
            return $surfaceArea ?? $quantity;
        }
        
        return $quantity;
    }

    /**
     * Calculate surface area in m² per unit for an item
     * 
     * Formula: (width_mm * height_mm) / 1,000,000
     * This returns the area for ONE unit, not multiplied by quantity.
     * 
     * @param array $itemData The item data array
     * @return float|null Area in m² per unit, or null if dimensions are missing
     */
    protected function calculateItemAreaPerUnit(array $itemData): ?float
    {
        $widthMm = $itemData['width_mm'] ?? null;
        $heightMm = $itemData['height_mm'] ?? null;
        
        // Return null if either dimension is missing or zero
        if (!$widthMm || !$heightMm || $widthMm <= 0 || $heightMm <= 0) {
            return null;
        }

        // Convert mm² to m²: (width * height) / 1,000,000
        // This is the area for ONE unit
        return ((float) $widthMm * (float) $heightMm) / 1000000;
    }

    /**
     * Recalculate all totals using area-based pricing
     * 
     * This is the SINGLE SOURCE OF TRUTH for quote totals calculation.
     * 
     * Product total calculation (area-based pricing):
     * 1. Calculate area per unit: (width_mm × height_mm) / 1,000,000
     * 2. Calculate product total: area_per_unit (m²) × unit_price (€/m²) × quantity
     * 
     * If dimensions are missing, fallback to: unit_price × quantity
     * 
     * This method updates:
     * - Each item's calculated_area and calculated_line_total for display
     * - Quote-level totals (subtotal, installation, delivery, discount, VAT, grand total)
     */
    public function recalculateTotals()
    {
        // Calculate subtotal from all items using area-based pricing
        $subtotal = 0;
        
        // Iterate through all items and calculate totals
        // IMPORTANT: We read from $this->items directly to get the latest values
        foreach ($this->items as $index => $itemData) {
            // Skip items without product, but initialize calculated values
            if (empty($itemData['product_id'])) {
                $this->items[$index]['calculated_area'] = null;
                $this->items[$index]['calculated_line_total'] = 0;
                continue;
            }

            // Step 1: Calculate surface area per unit (m²)
            // Formula: (width_mm × height_mm) / 1,000,000
            // This gives us the area for ONE unit
            $areaPerUnit = $this->calculateItemAreaPerUnit($itemData);
            $quantity = (float) ($itemData['quantity'] ?? 1);
            $unitPrice = (float) ($itemData['unit_price'] ?? 0);
            
            // Step 1b: Calculate linear meter per unit (lm)
            // Formula: 2 × (width_mm + height_mm) / 1000
            // This gives us the perimeter for ONE unit in meters
            $linearMeterPerUnit = null;
            if ($itemData['width_mm'] && $itemData['height_mm'] && 
                $itemData['width_mm'] > 0 && $itemData['height_mm'] > 0) {
                $linearMeterPerUnit = (2 * ((float) $itemData['width_mm'] + (float) $itemData['height_mm'])) / 1000;
                // Store calculated total linear meter (perimeter per unit × quantity) for display
                $this->items[$index]['calculated_linear_meter'] = round($linearMeterPerUnit * $quantity, 2);
            } else {
                $this->items[$index]['calculated_linear_meter'] = null;
            }
            
            // Step 2: Calculate product total using area-based pricing
            // Formula: area_per_unit (m²) × unit_price (€/m²) × quantity
            if ($areaPerUnit !== null && $areaPerUnit > 0) {
                // Area-based pricing: area (m²) × price (€/m²) × quantity
                $productTotal = $areaPerUnit * $unitPrice * $quantity;
                // Store calculated total area (area per unit × quantity) for display
                // This is what we show in the "Area (m²)" field
                $this->items[$index]['calculated_area'] = round($areaPerUnit * $quantity, 2);
            } else {
                // Fallback: no dimensions or zero area, use simple calculation
                // This treats it as if each unit has 1.0 m² area (backward compatibility)
                $productTotal = $unitPrice * $quantity;
                $this->items[$index]['calculated_area'] = null;
            }
            
            // Calculate service total using ServiceCalculationService
            // This handles different unit types (piece, sqm, lm) and enabled/disabled state
            $serviceTotal = 0;
            $serviceCalcService = app(ServiceCalculationService::class);
            
            // Create a temporary QuoteItem model for calculations
            $tempItem = new QuoteItem();
            $tempItem->product_id = $itemData['product_id'] ?? null;
            $tempItem->quantity = (float) ($itemData['quantity'] ?? 1);
            $tempItem->width_mm = $itemData['width_mm'] ?? null;
            $tempItem->height_mm = $itemData['height_mm'] ?? null;
            $tempItem->surface_area_m2 = $itemData['calculated_area'] ?? null;
            $tempItem->linear_meter = $itemData['calculated_linear_meter'] ?? null;

            // Track service IDs to prevent duplicates (safety check)
            // Note: Using service_id as array key already prevents duplicates, but this is an extra safety check
            $seenServiceIds = [];
            
            foreach ($itemData['selected_services'] ?? [] as $serviceId => $serviceData) {
                // Skip duplicate services (should not happen due to array key uniqueness, but safety check)
                if (in_array($serviceId, $seenServiceIds)) {
                    \Log::warning("Duplicate service detected in Livewire QuoteEditor: service_id {$serviceId} for quote_item. Skipping duplicate.");
                    continue;
                }
                $seenServiceIds[] = $serviceId;
                
                $service = \App\Models\Service::find($serviceId);
                if (!$service) {
                    continue;
                }

                $enabled = $serviceData['enabled'] ?? true;
                $quantity = isset($serviceData['quantity']) ? (float) $serviceData['quantity'] : null;
                $unitPrice = isset($serviceData['price_per_unit']) ? (float) $serviceData['price_per_unit'] : null;

                // Calculate service total using the service calculation service
                $serviceTotal += $serviceCalcService->calculateServiceTotal(
                    $tempItem,
                    $service,
                    $enabled,
                    $quantity,
                    $unitPrice
                );
            }
            
            // Calculate accessory total
            $accessoryTotal = 0;
            foreach ($itemData['selected_accessories'] ?? [] as $accessoryId => $accessoryData) {
                $accessory = \App\Models\Accessory::find($accessoryId);
                if ($accessory) {
                    $unitPrice = (float) $accessory->uniform_price;
                    $accessoryQuantity = (float) ($accessoryData['quantity'] ?? 1);
                    $accessoryTotal += $unitPrice * $accessoryQuantity;
                }
            }
            
            // Step 3: Calculate line subtotal (product + services + accessories)
            $lineSubtotal = $productTotal + $serviceTotal + $accessoryTotal;
            
            // Step 4: Apply item-level discount
            $itemDiscount = 0;
            if (($itemData['discount_type'] ?? 'none') === 'fixed') {
                $itemDiscount = (float) ($itemData['discount_value'] ?? 0);
            } elseif (($itemData['discount_type'] ?? 'none') === 'percentage') {
                $itemDiscount = ($lineSubtotal * (float) ($itemData['discount_value'] ?? 0)) / 100;
            }
            
            // Step 5: Calculate final line total (after discount)
            $lineTotal = $lineSubtotal - $itemDiscount;
            
            // Store calculated line total for display
            // This is what we show in the "Line Total (€)" field
            $this->items[$index]['calculated_line_total'] = round($lineTotal, 2);
            
            // Add to quote subtotal
            $subtotal += $lineTotal;
        }

        // Calculate installation cost
        $installationMultiplier = $this->installationMultiplierOverride 
            ?? SystemSetting::getValue('installation_multiplier', 0.15);
        $installationCost = $subtotal * (float) $installationMultiplier;

        // Calculate delivery cost
        $deliveryFeePerKm = SystemSetting::getValue('delivery_fee_per_km', 1.5);
        $deliveryCost = 0;
        if ($this->deliveryDistanceKm) {
            $deliveryCost = (float) $this->deliveryDistanceKm * (float) $deliveryFeePerKm;
        }

        // Calculate quote-level discount
        $totalDiscount = 0;
        if ($this->discountType === 'fixed') {
            $totalDiscount = (float) $this->discountValue;
        } elseif ($this->discountType === 'percentage') {
            $totalDiscount = ($subtotal * (float) $this->discountValue) / 100;
        }

        // Calculate net total (before VAT)
        $netTotal = $subtotal + $installationCost + $deliveryCost - $totalDiscount;

        // Calculate VAT
        $vatRate = $this->vatRate ?? SystemSetting::getValue('default_vat_rate', 27);
        $vatAmount = $netTotal * ((float) $vatRate / 100);

        // Calculate grand total
        $totalAmount = $netTotal + $vatAmount;

        // Update component properties
        $this->subtotal = round($subtotal, 2);
        $this->installationCost = round($installationCost, 2);
        $this->deliveryCost = round($deliveryCost, 2);
        $this->totalDiscount = round($totalDiscount, 2);
        $this->vatAmount = round($vatAmount, 2);
        $this->totalAmount = round($totalAmount, 2);
    }

    /**
     * Save the quote (create or update)
     */
    public function save()
    {
        // Validate required fields
        $this->validate([
            'customerId' => 'required|exists:customers,id',
            'status' => 'required|string',
            'quoteDate' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ], [
            'customerId.required' => 'Please select a customer.',
            'items.*.product_id.required' => 'Please select a product for all items.',
            'items.*.quantity.required' => 'Quantity is required.',
        ]);

        return DB::transaction(function () {
            $pricingService = app(QuotePricingService::class);

            if ($this->quoteId) {
                // Update existing quote
                $quote = Quote::findOrFail($this->quoteId);
                $this->authorize('update', $quote);
            } else {
                // Create new quote
                $quote = new Quote();
                $quote->user_id = Auth::id();
                $quote->quote_number = $this->generateQuoteNumber();
                $quote->version = 1;
            }

            // Update quote properties
            $quote->customer_id = $this->customerId;
            $quote->status = $this->status;
            $quote->quote_date = $this->quoteDate;
            $quote->valid_until = $this->validUntil;
            $quote->delivery_distance_km = $this->deliveryDistanceKm;
            $quote->installation_multiplier_override = $this->installationMultiplierOverride;
            $quote->discount_type = $this->discountType;
            $quote->discount_value = $this->discountValue;
            $quote->vat_rate = $this->vatRate;
            $quote->notes = $this->notes;

            $quote->save();

            // Delete existing items if updating
            if ($this->quoteId) {
                $quote->items()->delete();
            }

            // Create/update items
            // IMPORTANT: This loop handles BOTH:
            // 1. New items (added before saving) - they have temp_id but no database id
            // 2. Existing items (loaded from database) - they have both id and temp_id
            // 
            // When creating a new quote, all items are new and will be created.
            // When updating an existing quote, we delete all old items first (line 591),
            // then create new ones from the current $items array.
            foreach ($this->items as $index => $itemData) {
                if (!$itemData['product_id']) {
                    continue; // Skip items without product
                }

                // Calculate surface area per unit (m²)
                // Formula: (width_mm × height_mm) / 1,000,000
                $areaPerUnit = null;
                if ($itemData['width_mm'] && $itemData['height_mm'] && 
                    $itemData['width_mm'] > 0 && $itemData['height_mm'] > 0) {
                    $areaPerUnit = ((float) $itemData['width_mm'] * (float) $itemData['height_mm']) / 1000000;
                }
                
                // Calculate total surface area (area per unit × quantity) for storage
                $totalSurfaceArea = $areaPerUnit ? round($areaPerUnit * (float) $itemData['quantity'], 2) : null;
                
                // Calculate total linear meter (perimeter per unit × quantity) for storage
                // Formula: 2 × (width_mm + height_mm) / 1000 × quantity
                $totalLinearMeter = null;
                if ($itemData['width_mm'] && $itemData['height_mm'] && 
                    $itemData['width_mm'] > 0 && $itemData['height_mm'] > 0) {
                    $linearMeterPerUnit = (2 * ((float) $itemData['width_mm'] + (float) $itemData['height_mm'])) / 1000;
                    $totalLinearMeter = round($linearMeterPerUnit * (float) $itemData['quantity'], 2);
                }
                
                // Calculate product total using area-based pricing
                // Formula: area_per_unit (m²) × unit_price (€/m²) × quantity
                // If no dimensions, fallback to: unit_price × quantity
                $quantity = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];
                if ($areaPerUnit !== null && $areaPerUnit > 0) {
                    // Area-based pricing: area (m²) × price (€/m²) × quantity
                    $productTotal = $areaPerUnit * $unitPrice * $quantity;
                } else {
                    // Fallback: no dimensions, use simple calculation (backward compatibility)
                    $productTotal = $unitPrice * $quantity;
                }

                // Prepare custom_name - use null if empty string
                $customName = !empty($itemData['custom_name']) ? trim($itemData['custom_name']) : null;
                
                $item = QuoteItem::create([
                    'quote_id' => $quote->id,
                    'product_id' => $itemData['product_id'],
                    'custom_name' => $customName, // Store custom display name if provided
                    'quantity' => $itemData['quantity'],
                    'width_mm' => $itemData['width_mm'],
                    'height_mm' => $itemData['height_mm'],
                    'surface_area_m2' => $totalSurfaceArea, // Store total area (per unit × quantity) with 2 decimals
                    'linear_meter' => $totalLinearMeter, // Store total linear meter (perimeter per unit × quantity) with 2 decimals
                    'unit_price' => $itemData['unit_price'],
                    'product_total' => round($productTotal, 2), // Store calculated product total using area-based pricing
                    'discount_type' => $itemData['discount_type'],
                    'discount_value' => $itemData['discount_value'],
                    'notes' => $itemData['notes'] ?? '',
                    'sort_order' => $index,
                ]);

                // Attach services with pivot data using ServiceCalculationService
                // Prevent duplicate services - each service can only be added once per quote item
                $serviceCalcService = app(ServiceCalculationService::class);
                $seenServiceIds = []; // Track to prevent duplicates
                
                foreach ($itemData['selected_services'] ?? [] as $serviceId => $serviceData) {
                    // Skip duplicate services (extra safety check)
                    // Note: Using service_id as array key already prevents duplicates, but this ensures data integrity
                    // The database primary key will also enforce uniqueness as a final safety net
                    if (in_array($serviceId, $seenServiceIds)) {
                        \Log::warning("Duplicate service detected when saving quote: service_id {$serviceId} for quote_item_id {$item->id}. Skipping duplicate.");
                        continue;
                    }
                    $seenServiceIds[] = $serviceId;
                    
                    $service = \App\Models\Service::find($serviceId);
                    if (!$service) {
                        continue;
                    }

                    $enabled = $serviceData['enabled'] ?? true;
                    $quantity = isset($serviceData['quantity']) ? (float) $serviceData['quantity'] : null;
                    $pricePerUnit = isset($serviceData['price_per_unit']) ? (float) $serviceData['price_per_unit'] : null;

                    // Calculate service total
                    $serviceTotal = $serviceCalcService->calculateServiceTotal(
                        $item,
                        $service,
                        $enabled,
                        $quantity,
                        $pricePerUnit
                    );

                    // Store quantity (use default if not provided)
                    $storedQuantity = $quantity ?? $serviceCalcService->getDefaultQuantity($item, $service) ?? 1.0;

                    // Use attach() to add the service
                    // The database primary key will prevent duplicates if somehow one gets through
                    $item->services()->attach($serviceId, [
                        'enabled' => $enabled,
                        'price_per_unit' => $pricePerUnit ?? 0,
                        'quantity' => $storedQuantity,
                        'total' => $serviceTotal,
                    ]);
                }

                // Attach accessories with pivot data
                foreach ($itemData['selected_accessories'] ?? [] as $accessoryId => $accessoryData) {
                    $accessory = \App\Models\Accessory::find($accessoryId);
                    if ($accessory) {
                        $accessoryQuantity = $accessoryData['quantity'] ?? 1;
                        $unitPrice = $accessory->uniform_price;
                        $accessoryTotal = $unitPrice * $accessoryQuantity;

                        $item->accessories()->attach($accessoryId, [
                            'quantity' => $accessoryQuantity,
                            'unit_price' => $unitPrice,
                            'total' => $accessoryTotal,
                        ]);
                    }
                }
            }

            // Recalculate and save all totals
            $quote = $pricingService->recalculateAndSave($quote);

            $this->quoteId = $quote->id;
            $this->quoteNumber = $quote->quote_number;

            session()->flash('message', 'Quote saved successfully.');
        });
    }

    /**
     * Create a new version of this quote
     */
    public function createVersion()
    {
        if (!$this->quoteId) {
            session()->flash('error', 'Cannot create version: Quote must be saved first.');
            return;
        }

        $quote = Quote::findOrFail($this->quoteId);
        $this->authorize('update', $quote);

        $duplicateService = app(QuoteDuplicateService::class);
        $newQuote = $duplicateService->duplicateWithItems($quote);

        // Redirect to edit the new version
        return redirect()->route('sales.quotes.edit', $newQuote);
    }

    /**
     * Generate PDF for the current quote
     * 
     * This implements UC-S-01 step 10 from the SRS: "Generate a printable PDF document
     * from a finalized quote."
     * 
     * Steps:
     * 1. Ensure quote is saved (has an ID)
     * 2. Recalculate totals to ensure they're up to date
     * 3. Generate PDF using QuotePdfService
     * 4. Show success message with download link
     */
    public function generatePdf()
    {
        if (!$this->quoteId) {
            session()->flash('error', 'Please save the quote before generating PDF.');
            return;
        }

        $quote = Quote::findOrFail($this->quoteId);
        $this->authorize('view', $quote);

        // Recalculate totals to ensure PDF has accurate data
        $pricingService = app(QuotePricingService::class);
        $quote = $pricingService->recalculateAndSave($quote);

        // Generate PDF
        $pdfService = app(QuotePdfService::class);
        $pdfPath = $pdfService->generatePdf($quote);

        // Refresh quote to get updated PDF info
        $quote->refresh();

        // Show success message
        session()->flash('message', 'PDF generated successfully!');
        session()->flash('pdf_download_url', $pdfService->getDownloadUrl($quote));

        // Reload quote data to show PDF info
        $this->loadQuote();
    }

    /**
     * Generate quote number (same logic as in CreateQuote page)
     */
    protected function generateQuoteNumber(): string
    {
        $year = date('Y');
        $prefix = "AJ-{$year}-";

        $lastQuote = \Illuminate\Support\Facades\DB::table('quotes')
            ->where('quote_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(quote_number, "-", 3), "-", -1) AS UNSIGNED) DESC')
            ->first();

        if ($lastQuote) {
            preg_match('/AJ-\d+-(\d+)-V\d+/', $lastQuote->quote_number, $matches);
            $nextNumber = isset($matches[1]) ? (int)$matches[1] + 1 : 1;
        } else {
            $nextNumber = 1;
        }

        $sequential = str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        return "{$prefix}{$sequential}-V1";
    }

    /**
     * Create a new customer inline
     */
    public function createCustomer()
    {
        $this->validate([
            'newCustomerName' => 'required|string|max:255',
            'newCustomerEmail' => 'nullable|email|max:255',
            'newCustomerPhone' => 'nullable|string|max:50',
        ]);

        $customer = Customer::create([
            'name' => $this->newCustomerName,
            'email' => $this->newCustomerEmail,
            'phone' => $this->newCustomerPhone,
            'is_active' => true,
        ]);

        $this->customerId = $customer->id;
        $this->showCustomerModal = false;
        $this->newCustomerName = '';
        $this->newCustomerEmail = '';
        $this->newCustomerPhone = '';

        session()->flash('message', 'Customer created successfully.');
    }

    public function render()
    {
        // Get customers for dropdown
        $customers = Customer::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Get products for dropdown
        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Get statuses
        $statuses = SystemSetting::getValue('quote_statuses', [
            'draft',
            'sent',
            'under_review',
            'accepted',
            'rejected',
            'closed',
        ]);

        return view('livewire.quote-editor', [
            'customers' => $customers,
            'products' => $products,
            'statuses' => array_combine($statuses, array_map('ucfirst', $statuses)),
        ]);
    }
}
