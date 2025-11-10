<?php

namespace App\Services;

use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service class for duplicating quotes with all related items.
 * 
 * Implements UC-S-02 from SRS: "Modify an existing quote by creating a new version (copy),
 * keeping all items from the previous version so the Sales user can change them."
 */
class QuoteDuplicateService
{
    /**
     * Duplicate a quote with all its items, services, and accessories.
     * 
     * This method implements UC-S-02 from the SRS:
     * - Creates a new quote version (copy)
     * - Keeps all items from the previous version
     * - Copies all related data (services, accessories, quantities, sizes, discounts)
     * - Original quote remains unchanged as historical record
     * - New version is fully editable and independent
     * 
     * @param Quote $originalQuote The quote to duplicate
     * @param int|null $userId Optional user ID (defaults to current authenticated user)
     * @return Quote The newly created quote version
     * @throws \Exception If duplication fails
     */
    public function duplicateWithItems(Quote $originalQuote, ?int $userId = null): Quote
    {
        // Use database transaction to ensure consistency
        return DB::transaction(function () use ($originalQuote, $userId) {
            
            // Step 1: Load original quote with ALL relations using eager loading
            // This prevents N+1 queries and ensures we have all data
            $originalQuote->load([
                'items.services',
                'items.accessories',
                'customer',
                'user',
            ]);

            // Step 2: Calculate next version number
            $nextVersion = $this->getNextVersionNumber($originalQuote);
            
            // Step 3: Generate new quote number with incremented version
            // Format: AJ-YYYY-NNNNN-V{N} (e.g., AJ-2025-00001-V2)
            $newQuoteNumber = $this->generateQuoteNumberForVersion($originalQuote, $nextVersion);

            // Step 4: Create new Quote record by cloning the original
            // Copy all quote-level data, but set appropriate fields for new version
            $newQuote = Quote::create([
                // Version information
                'quote_number' => $newQuoteNumber,
                'version' => $nextVersion,
                'parent_quote_id' => $this->getBaseQuoteId($originalQuote),
                
                // Customer and user
                'customer_id' => $originalQuote->customer_id,
                'user_id' => $userId ?? auth()->id(),
                
                // Status - new version always starts as draft
                'status' => 'draft',
                
                // Dates
                'quote_date' => now(),
                'valid_until' => $originalQuote->valid_until,
                
                // Delivery and installation
                'delivery_distance_km' => $originalQuote->delivery_distance_km,
                'delivery_cost' => $originalQuote->delivery_cost,
                'installation_cost' => $originalQuote->installation_cost,
                'installation_multiplier_override' => $originalQuote->installation_multiplier_override,
                
                // Discounts and VAT
                'discount_type' => $originalQuote->discount_type,
                'discount_value' => $originalQuote->discount_value,
                'vat_rate' => $originalQuote->vat_rate,
                
                // Totals (copied from original, will be recalculated if needed)
                'subtotal' => $originalQuote->subtotal,
                'total_discount' => $originalQuote->total_discount,
                'vat_amount' => $originalQuote->vat_amount,
                'total_amount' => $originalQuote->total_amount,
                
                // Notes
                'notes' => $originalQuote->notes,
                
                // PDF fields - new version has no PDF yet
                'pdf_generated_at' => null,
                'pdf_path' => null,
            ]);

            // Step 5: Copy all QuoteItems from original quote
            foreach ($originalQuote->items as $originalItem) {
                // Create new QuoteItem pointing to the new Quote (new quote_id)
                // Copy all relevant fields: product, quantity, dimensions, prices, discounts, etc.
                $newItem = $newQuote->items()->create([
                    'product_id' => $originalItem->product_id,
                    'custom_name' => $originalItem->custom_name, // Copy custom display name if set
                    'quantity' => $originalItem->quantity,
                    'width_mm' => $originalItem->width_mm,
                    'height_mm' => $originalItem->height_mm,
                    'surface_area_m2' => $originalItem->surface_area_m2,
                    'unit_price' => $originalItem->unit_price,
                    'product_total' => $originalItem->product_total,
                    'service_total' => $originalItem->service_total,
                    'accessory_total' => $originalItem->accessory_total,
                    'line_total' => $originalItem->line_total,
                    'discount_type' => $originalItem->discount_type,
                    'discount_value' => $originalItem->discount_value,
                    'notes' => $originalItem->notes,
                    'sort_order' => $originalItem->sort_order,
                ]);

                // Step 6: Copy all services linked to this item (pivot records)
                // Each service has: service_id, price_per_unit, quantity, total
                foreach ($originalItem->services as $service) {
                    $newItem->services()->attach($service->id, [
                        'price_per_unit' => $service->pivot->price_per_unit,
                        'quantity' => $service->pivot->quantity,
                        'total' => $service->pivot->total,
                    ]);
                }

                // Step 7: Copy all accessories linked to this item (pivot records)
                // Each accessory has: accessory_id, quantity, unit_price, total
                foreach ($originalItem->accessories as $accessory) {
                    $newItem->accessories()->attach($accessory->id, [
                        'quantity' => $accessory->pivot->quantity,
                        'unit_price' => $accessory->pivot->unit_price,
                        'total' => $accessory->pivot->total,
                    ]);
                }
            }

            // Refresh the new quote to ensure all relationships are loaded
            $newQuote->refresh();
            $newQuote->load(['items', 'items.services', 'items.accessories', 'customer', 'user']);

            // Log the duplication for audit trail
            Log::info('Quote duplicated', [
                'original_quote_id' => $originalQuote->id,
                'original_quote_number' => $originalQuote->quote_number,
                'new_quote_id' => $newQuote->id,
                'new_quote_number' => $newQuote->quote_number,
                'version' => $nextVersion,
                'items_copied' => $originalQuote->items->count(),
            ]);

            return $newQuote;
        });
    }

    /**
     * Get the base quote ID (the original quote, not a version)
     * 
     * If the quote being duplicated is itself a version, we find its parent.
     * Otherwise, we use the quote itself as the base.
     */
    protected function getBaseQuoteId(Quote $quote): int
    {
        return $quote->parent_quote_id ?? $quote->id;
    }

    /**
     * Get the next version number for a quote family
     * 
     * Finds the highest version number in the quote family (original + all versions)
     * and increments it by 1.
     */
    protected function getNextVersionNumber(Quote $quote): int
    {
        // Find the base quote (original, not a version)
        $baseQuoteId = $this->getBaseQuoteId($quote);
        $baseQuote = Quote::find($baseQuoteId);

        // Find the highest version number in this quote family
        // This includes the base quote and all its versions
        $maxVersion = Quote::where(function ($query) use ($baseQuoteId) {
            $query->where('id', $baseQuoteId)
                  ->orWhere('parent_quote_id', $baseQuoteId);
        })->max('version');

        // Return next version number
        return ($maxVersion ?? 0) + 1;
    }

    /**
     * Generate quote number for a new version based on parent quote
     * 
     * Extracts the base number from parent (e.g., "AJ-2025-00001-V1" -> "AJ-2025-00001")
     * and appends the new version number (e.g., "AJ-2025-00001-V2")
     */
    protected function generateQuoteNumberForVersion(Quote $parentQuote, int $version): string
    {
        // Extract base number from parent (e.g., "AJ-2025-00001-V1" -> "AJ-2025-00001")
        if (preg_match('/^(AJ-\d+-\d+)-V\d+$/', $parentQuote->quote_number, $matches)) {
            $baseNumber = $matches[1];
            return "{$baseNumber}-V{$version}";
        }

        // If parent quote number format is unexpected, try to extract base from original
        $baseQuoteId = $this->getBaseQuoteId($parentQuote);
        if ($baseQuoteId !== $parentQuote->id) {
            $baseQuote = Quote::find($baseQuoteId);
            if ($baseQuote && preg_match('/^(AJ-\d+-\d+)-V\d+$/', $baseQuote->quote_number, $matches)) {
                $baseNumber = $matches[1];
                return "{$baseNumber}-V{$version}";
            }
        }

        // Fallback: if we can't parse the format, generate a new number
        // This should not happen in normal operation
        Log::warning('Could not parse quote number format', [
            'quote_id' => $parentQuote->id,
            'quote_number' => $parentQuote->quote_number,
        ]);

        return $this->generateNewQuoteNumber();
    }

    /**
     * Generate a completely new quote number
     * 
     * Format: AJ-YYYY-NNNNN-V1
     * This is used as fallback if version number generation fails
     */
    protected function generateNewQuoteNumber(): string
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

