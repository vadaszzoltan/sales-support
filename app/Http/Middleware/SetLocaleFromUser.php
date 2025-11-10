<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to set application locale based on authenticated user's preference.
 * 
 * For authenticated users: uses their preferred locale from database.
 * For guests: uses default locale from config.
 * 
 * This ensures all UI labels, product names, and other translatable content
 * are displayed in the user's preferred language.
 */
class SetLocaleFromUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get locale from authenticated user or use default
        if (auth()->check()) {
            // Authenticated user: use their preferred locale
            $locale = auth()->user()->getPreferredLocale();
        } else {
            // Guest user: use default locale from config
            $locale = config('locales.ui_default', config('locales.default', 'en'));
        }

        // Set application locale
        app()->setLocale($locale);

        return $next($request);
    }
}
