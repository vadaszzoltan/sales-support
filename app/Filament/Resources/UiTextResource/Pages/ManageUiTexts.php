<?php

namespace App\Filament\Resources\UiTextResource\Pages;

use App\Filament\Resources\UiTextResource;
use App\Helpers\TranslationHelper;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageUiTexts extends ManageRecords
{
    protected static string $resource = UiTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    
    /**
     * Hook called after a record is created
     */
    protected function afterCreate(): void
    {
        // Clear cache for the new translation key
        if ($record = $this->record) {
            TranslationHelper::clearCache($record->key);
        }
    }
    
    /**
     * Hook called after a record is updated
     */
    protected function afterUpdate(): void
    {
        // Clear cache for the updated translation key
        if ($record = $this->record) {
            TranslationHelper::clearCache($record->key);
        }
    }
    
    /**
     * Hook called after a record is deleted
     */
    protected function afterDelete(): void
    {
        // Clear cache for the deleted translation key (if we have access to it)
        // Note: Record is already deleted at this point, so we can't access it
        // Cache will expire naturally, or we can clear all cache
        TranslationHelper::clearCache();
    }
}

