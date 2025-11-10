<?php

namespace App\Filament\Resources\ProductServiceCompatibilityResource\Pages;

use App\Filament\Resources\ProductServiceCompatibilityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductServiceCompatibility extends EditRecord
{
    protected static string $resource = ProductServiceCompatibilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
