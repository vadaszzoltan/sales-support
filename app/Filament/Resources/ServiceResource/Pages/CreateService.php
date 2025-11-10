<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use App\Models\Service;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    /**
     * Handle data before creating the service
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default pricing_mode if not provided
        if (empty($data['pricing_mode'])) {
            $data['pricing_mode'] = Service::PRICING_PER_SQM;
        }

        return $data;
    }

    /**
     * After service is created, sync product pricing
     */
    protected function afterCreate(): void
    {
        $service = $this->record;
        
        // The Repeater with ->relationship() should automatically handle saving product prices
        // But we need to ensure unit_type is set based on pricing_mode
        foreach ($service->productPricing as $pricing) {
            // Map pricing_mode to unit_type for backward compatibility
            $unitType = match($service->pricing_mode) {
                Service::PRICING_PER_SQM => 'sqm',
                Service::PRICING_PER_LM => 'lm',
                Service::PRICING_PER_PIECE => 'piece',
                default => 'sqm',
            };
            
            $pricing->update(['unit_type' => $unitType]);
        }
    }
}
