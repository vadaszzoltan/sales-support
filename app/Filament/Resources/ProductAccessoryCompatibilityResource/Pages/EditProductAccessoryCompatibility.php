<?php

namespace App\Filament\Resources\ProductAccessoryCompatibilityResource\Pages;

use App\Filament\Resources\ProductAccessoryCompatibilityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductAccessoryCompatibility extends EditRecord
{
    protected static string $resource = ProductAccessoryCompatibilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
