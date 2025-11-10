<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use App\Models\Service;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * After service is updated, sync product pricing unit_type
     */
    protected function afterSave(): void
    {
        $service = $this->record;
        
        // Update unit_type for all product pricing based on pricing_mode
        // This ensures consistency between pricing_mode and unit_type
        $unitType = match($service->pricing_mode) {
            Service::PRICING_PER_SQM => 'sqm',
            Service::PRICING_PER_LM => 'lm',
            Service::PRICING_PER_PIECE => 'piece',
            default => 'sqm',
        };
        
        foreach ($service->productPricing as $pricing) {
            $pricing->update(['unit_type' => $unitType]);
        }
    }
}
