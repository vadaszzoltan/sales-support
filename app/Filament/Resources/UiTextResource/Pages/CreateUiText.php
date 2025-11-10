<?php

namespace App\Filament\Resources\UiTextResource\Pages;

use App\Filament\Resources\UiTextResource;
use App\Helpers\TranslationHelper;
use Filament\Resources\Pages\CreateRecord;

class CreateUiText extends CreateRecord
{
    protected static string $resource = UiTextResource::class;
    
    /**
     * Hook called after the record is created
     */
    protected function afterCreate(): void
    {
        // Clear cache for the new translation key so it's immediately available
        TranslationHelper::clearCache($this->record->key);
    }
}
