<?php

namespace App\Filament\Resources\ProductAccessoryCompatibilityResource\Pages;

use App\Filament\Resources\ProductAccessoryCompatibilityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductAccessoryCompatibilities extends ListRecords
{
    protected static string $resource = ProductAccessoryCompatibilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
