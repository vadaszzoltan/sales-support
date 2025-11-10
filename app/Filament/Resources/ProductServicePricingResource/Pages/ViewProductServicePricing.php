<?php

namespace App\Filament\Resources\ProductServicePricingResource\Pages;

use App\Filament\Resources\ProductServicePricingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProductServicePricing extends ViewRecord
{
    protected static string $resource = ProductServicePricingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
