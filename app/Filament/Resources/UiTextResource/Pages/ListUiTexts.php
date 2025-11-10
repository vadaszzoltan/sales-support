<?php

namespace App\Filament\Resources\UiTextResource\Pages;

use App\Filament\Resources\UiTextResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUiTexts extends ListRecords
{
    protected static string $resource = UiTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
