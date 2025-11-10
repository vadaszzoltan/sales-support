<?php

namespace App\Filament\Resources\ProductServicePricingResource\Pages;

use App\Filament\Resources\ProductServicePricingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductServicePricings extends ListRecords
{
    protected static string $resource = ProductServicePricingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
