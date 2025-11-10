<?php

namespace App\Filament\Resources\UiTextResource\Pages;

use App\Filament\Resources\UiTextResource;
use App\Helpers\TranslationHelper;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUiText extends EditRecord
{
    protected static string $resource = UiTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    /**
     * Hook called after the record is updated
     */
    protected function afterSave(): void
    {
        // Clear cache for the updated translation key so changes are immediately visible
        TranslationHelper::clearCache($this->record->key);
    }
    
    /**
     * Hook called after the record is deleted
     */
    protected function afterDelete(): void
    {
        // Clear cache for all translations since we deleted a key
        // (We could track the key before deletion, but clearing all is safer)
        TranslationHelper::clearCache();
    }
}
