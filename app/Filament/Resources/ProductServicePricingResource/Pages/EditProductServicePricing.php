<?php

namespace App\Filament\Resources\ProductServicePricingResource\Pages;

use App\Filament\Resources\ProductServicePricingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductServicePricing extends EditRecord
{
    protected static string $resource = ProductServicePricingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
