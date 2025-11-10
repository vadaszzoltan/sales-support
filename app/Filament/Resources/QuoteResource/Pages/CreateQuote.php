<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Models\Quote;
use App\Services\QuotePricingService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    /**
     * Store parent quote for copying data and items
     */
    protected ?Quote $parentQuote = null;

    /**
     * Get the parent quote ID from query parameters
     */
    protected function getParentQuoteId(): ?int
    {
        $parent = request()->query('parent');
        return $parent ? (int) $parent : null;
    }

    /**
     * Load parent quote data when creating a new version
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $parentId = $this->getParentQuoteId();
        
        if ($parentId) {
            $parentQuote = Quote::with(['items.services', 'items.accessories', 'customer'])->find($parentId);
            
            if ($parentQuote) {
                // Copy all quote data from parent
                $data = [
                    'customer_id' => $parentQuote->customer_id,
                    'status' => 'draft', // New version starts as draft
                    'quote_date' => now(),
                    'valid_until' => $parentQuote->valid_until,
                    'delivery_distance_km' => $parentQuote->delivery_distance_km,
                    'delivery_cost' => $parentQuote->delivery_cost,
                    'installation_cost' => $parentQuote->installation_cost,
                    'installation_multiplier_override' => $parentQuote->installation_multiplier_override,
                    'discount_type' => $parentQuote->discount_type,
                    'discount_value' => $parentQuote->discount_value,
                    'subtotal' => $parentQuote->subtotal,
                    'total_discount' => $parentQuote->total_discount,
                    'vat_rate' => $parentQuote->vat_rate,
                    'vat_amount' => $parentQuote->vat_amount,
                    'total_amount' => $parentQuote->total_amount,
                    'notes' => $parentQuote->notes,
                    'parent_quote_id' => $parentQuote->id,
                ];

                // Store parent quote and items for later use
                $this->parentQuote = $parentQuote;
            }
        }

        return $data;
    }

    /**
     * Handle data before creating the quote
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Always set user_id to current authenticated user
        $data['user_id'] = auth()->id();

        $parentId = $this->getParentQuoteId();
        
        if ($parentId) {
            // Creating a new version
            $parentQuote = Quote::with(['items.services', 'items.accessories'])->find($parentId);
            
            if ($parentQuote) {
                // Calculate next version number
                $nextVersion = $this->getNextVersionNumber($parentQuote);
                
                // Generate quote number with new version
                $data['quote_number'] = $this->generateQuoteNumberForVersion($parentQuote, $nextVersion);
                $data['version'] = $nextVersion;
                $data['parent_quote_id'] = $parentQuote->id;
                
                // Store parent quote for copying items after creation
                $this->parentQuote = $parentQuote;
            }
        } else {
            // Creating a new quote (not a version)
            if (empty($data['quote_number'])) {
                $data['quote_number'] = $this->generateQuoteNumber();
            }
            $data['version'] = $data['version'] ?? 1;
        }

        // Set default values for required fields if not set
        $data['subtotal'] = $data['subtotal'] ?? 0;
        $data['total_discount'] = $data['total_discount'] ?? 0;
        $data['vat_amount'] = $data['vat_amount'] ?? 0;
        $data['total_amount'] = $data['total_amount'] ?? 0;
        $data['delivery_cost'] = $data['delivery_cost'] ?? 0;
        $data['installation_cost'] = $data['installation_cost'] ?? 0;
        $data['discount_type'] = $data['discount_type'] ?? 'none';
        $data['discount_value'] = $data['discount_value'] ?? 0;
        $data['vat_rate'] = $data['vat_rate'] ?? 27;

        return $data;
    }

    /**
     * After quote is created, handle items
     * 
     * This method handles TWO scenarios:
     * 1. Creating a new version (copy items from parent)
     * 2. Creating a new quote with items from the Repeater form
     */
    protected function afterCreate(): void
    {
        $parentId = $this->getParentQuoteId();
        $newQuote = $this->record;

        if ($parentId && isset($this->parentQuote)) {
            // Scenario 1: Creating a new version - copy items from parent
            $parentQuote = $this->parentQuote;

            // Copy all quote items from parent
            foreach ($parentQuote->items as $parentItem) {
                $newItem = $newQuote->items()->create([
                    'product_id' => $parentItem->product_id,
                    'quantity' => $parentItem->quantity,
                    'width_mm' => $parentItem->width_mm,
                    'height_mm' => $parentItem->height_mm,
                    'surface_area_m2' => $parentItem->surface_area_m2,
                    'unit_price' => $parentItem->unit_price,
                    'product_total' => $parentItem->product_total,
                    'service_total' => $parentItem->service_total,
                    'accessory_total' => $parentItem->accessory_total,
                    'line_total' => $parentItem->line_total,
                    'discount_type' => $parentItem->discount_type,
                    'discount_value' => $parentItem->discount_value,
                    'notes' => $parentItem->notes,
                    'sort_order' => $parentItem->sort_order,
                ]);

                // Copy services from parent item
                foreach ($parentItem->services as $service) {
                    $newItem->services()->attach($service->id, [
                        'price_per_unit' => $service->pivot->price_per_unit,
                        'quantity' => $service->pivot->quantity,
                        'total' => $service->pivot->total,
                    ]);
                }

                // Copy accessories from parent item
                foreach ($parentItem->accessories as $accessory) {
                    $newItem->accessories()->attach($accessory->id, [
                        'quantity' => $accessory->pivot->quantity,
                        'unit_price' => $accessory->pivot->unit_price,
                        'total' => $accessory->pivot->total,
                    ]);
                }
            }
        } else {
            // Scenario 2: Creating a new quote - items come from Repeater form
            // The Repeater component automatically saves items via the relationship,
            // but we need to calculate surface_area_m2 and ensure totals are correct
            
            $pricingService = app(QuotePricingService::class);
            
            // Recalculate all items to ensure surface_area_m2, linear_meter and totals are correct
            // Also handle services that were added via the nested Repeater
            $serviceCalcService = app(\App\Services\ServiceCalculationService::class);
            
            foreach ($newQuote->items as $item) {
                // Calculate surface area and linear meter if dimensions are provided
                if ($item->width_mm && $item->height_mm && $item->width_mm > 0 && $item->height_mm > 0) {
                    // Calculate surface area
                    $areaPerUnit = ((float) $item->width_mm * (float) $item->height_mm) / 1000000;
                    $item->surface_area_m2 = round($areaPerUnit * (float) $item->quantity, 2);
                    
                    // Calculate linear meter (perimeter)
                    $linearMeterPerUnit = (2 * ((float) $item->width_mm + (float) $item->height_mm)) / 1000;
                    $item->linear_meter = round($linearMeterPerUnit * (float) $item->quantity, 2);
                }
                
                // Recalculate service totals for services added via Repeater
                // The Repeater should have already saved services, but we need to recalculate totals
                $item->load('services');
                foreach ($item->services as $service) {
                    $enabled = $service->pivot->enabled ?? true;
                    $quantity = isset($service->pivot->quantity) ? (float) $service->pivot->quantity : null;
                    $pricePerUnit = isset($service->pivot->price_per_unit) ? (float) $service->pivot->price_per_unit : null;
                    
                    $serviceTotal = $serviceCalcService->calculateServiceTotal(
                        $item,
                        $service,
                        $enabled,
                        $quantity,
                        $pricePerUnit
                    );
                    
                    // Update pivot table with calculated total
                    $item->services()->updateExistingPivot($service->id, [
                        'total' => $serviceTotal,
                    ]);
                }
                
                // Recalculate item totals using the pricing service
                $itemTotals = $pricingService->calculateItemTotal($item);
                $item->update($itemTotals);
            }
            
            // Recalculate quote totals
            $quoteTotals = $pricingService->calculateQuoteTotal($newQuote);
            $newQuote->update($quoteTotals);
        }
    }

    /**
     * Get the next version number for a parent quote
     */
    protected function getNextVersionNumber(Quote $parentQuote): int
    {
        // Find the highest version number for this quote family
        $baseQuote = $parentQuote->parent_quote_id ? Quote::find($parentQuote->parent_quote_id) : $parentQuote;
        
        $maxVersion = Quote::where(function ($query) use ($baseQuote) {
            $query->where('id', $baseQuote->id)
                  ->orWhere('parent_quote_id', $baseQuote->id);
        })->max('version');

        return ($maxVersion ?? 0) + 1;
    }

    /**
     * Generate quote number for a new version based on parent quote
     */
    protected function generateQuoteNumberForVersion(Quote $parentQuote, int $version): string
    {
        // Extract base number from parent (e.g., "AJ-2025-00001-V1" -> "AJ-2025-00001")
        if (preg_match('/^(AJ-\d+-\d+)-V\d+$/', $parentQuote->quote_number, $matches)) {
            $baseNumber = $matches[1];
            return "{$baseNumber}-V{$version}";
        }

        // Fallback: generate new number if parent format is unexpected
        return $this->generateQuoteNumber();
    }

    /**
     * Generate a unique quote number in format: AJ-YYYY-NNNNN-V1
     */
    protected function generateQuoteNumber(): string
    {
        $year = date('Y');
        $prefix = "AJ-{$year}-";

        // Get the highest sequential number for this year
        $lastQuote = DB::table('quotes')
            ->where('quote_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(quote_number, "-", 3), "-", -1) AS UNSIGNED) DESC')
            ->first();

        if ($lastQuote) {
            // Extract the number from the last quote (e.g., "AJ-2024-123-V1" -> 123)
            preg_match('/AJ-\d+-(\d+)-V\d+/', $lastQuote->quote_number, $matches);
            $nextNumber = isset($matches[1]) ? (int)$matches[1] + 1 : 1;
        } else {
            $nextNumber = 1;
        }

        // Format with leading zeros (e.g., 00123)
        $sequential = str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        return "{$prefix}{$sequential}-V1";
    }
}
