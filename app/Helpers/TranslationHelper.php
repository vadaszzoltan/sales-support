<?php

namespace App\Helpers;

use App\Models\UiText;
use Illuminate\Support\Facades\Cache;

/**
 * Helper class for translation functions
 */
class TranslationHelper
{
    /**
     * Get UI label translation with fallback
     * 
     * @param string $key The translation key (e.g., 'quote.status.draft')
     * @param string|null $locale The locale code (en, ro, hu). If null, uses current app locale
     * @param string $default The default value if translation is not found
     * @return string The translated text
     */
    public static function uiLabel(string $key, ?string $locale = null, string $default = ''): string
    {
        // Get locale - use provided, current app locale, or default
        $locale = $locale ?? app()->getLocale() ?? config('locales.default', 'en');
        
        // Validate locale
        $supportedLocales = config('locales.supported', ['en', 'ro', 'hu']);
        if (!in_array($locale, $supportedLocales)) {
            $locale = config('locales.default', 'en');
        }
        
        // Try to get from cache first (cache key includes locale)
        $cacheKey = "ui_text_{$key}_{$locale}";
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $locale, $default, $supportedLocales) {
            // Get UI text from database
            $uiText = UiText::where('key', $key)->first();
            
            if (!$uiText) {
                return $default ?: $key;
            }
            
            // Try to get value for requested locale
            $value = $uiText->getValue($locale);
            
            // If not found, try fallback chain: requested locale -> default locale -> en
            if (empty($value)) {
                $defaultLocale = config('locales.default', 'en');
                
                // Try default locale
                if ($locale !== $defaultLocale) {
                    $value = $uiText->getValue($defaultLocale);
                }
                
                // If still not found, try English as final fallback
                if (empty($value) && $locale !== 'en' && $defaultLocale !== 'en') {
                    $value = $uiText->getValue('en');
                }
            }
            
            // Return value or default
            return $value ?: $default ?: $key;
        });
    }
    
    /**
     * Clear UI text cache for a specific key or all keys
     * 
     * @param string|null $key The key to clear cache for, or null to clear all
     * @return void
     */
    public static function clearCache(?string $key = null): void
    {
        if ($key) {
            $supportedLocales = config('locales.supported', ['en', 'ro', 'hu']);
            foreach ($supportedLocales as $locale) {
                Cache::forget("ui_text_{$key}_{$locale}");
            }
        } else {
            // Clear all UI text caches (this is a simple approach)
            // In production, you might want to use cache tags if supported
            Cache::flush();
        }
    }
}

