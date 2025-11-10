<?php

namespace App\Filament\Resources\ProductServiceCompatibilityResource\Pages;

use App\Filament\Resources\ProductServiceCompatibilityResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProductServiceCompatibility extends ViewRecord
{
    protected static string $resource = ProductServiceCompatibilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
