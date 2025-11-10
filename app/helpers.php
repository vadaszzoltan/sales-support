<?php

use App\Helpers\TranslationHelper;

if (!function_exists('ui_label')) {
    /**
     * Get UI label translation with fallback
     * 
     * This is a global helper function that can be used anywhere in the application.
     * 
     * @param string $key The translation key (e.g., 'quote.status.draft')
     * @param string|null $locale The locale code (en, ro, hu). If null, uses current app locale
     * @param string $default The default value if translation is not found
     * @return string The translated text
     * 
     * @example
     * ui_label('quote.status.draft') // Returns translation in current locale
     * ui_label('quote.status.draft', 'ro') // Returns Romanian translation
     * ui_label('quote.status.draft', 'hu', 'Vázlat') // Returns Hungarian translation or 'Vázlat' if not found
     */
    function ui_label(string $key, ?string $locale = null, string $default = ''): string
    {
        return TranslationHelper::uiLabel($key, $locale, $default);
    }
}

