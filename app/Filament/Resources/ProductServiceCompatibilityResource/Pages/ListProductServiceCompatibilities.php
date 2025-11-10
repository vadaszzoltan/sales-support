<?php

namespace App\Filament\Resources\ProductServiceCompatibilityResource\Pages;

use App\Filament\Resources\ProductServiceCompatibilityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductServiceCompatibilities extends ListRecords
{
    protected static string $resource = ProductServiceCompatibilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
