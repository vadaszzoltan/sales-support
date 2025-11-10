<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow access to login and logout routes - users need to log in first!
        if ($request->is('admin/login') || 
            $request->is('admin/logout') ||
            $request->routeIs('filament.admin.auth.*')) {
            return $next($request);
        }

        // Only check admin status if user is authenticated
        // If not authenticated, Filament's Authenticate middleware will handle it
        if (auth()->check()) {
            $user = auth()->user();
            // User must be admin AND approved to access Filament
            if (!$user->isAdmin() || !$user->approved) {
                abort(403, 'Access denied. Admin privileges and approval required.');
            }
        }

        // If authenticated and is admin, allow access
        // If not authenticated, let Filament's auth middleware handle it
        return $next($request);
    }
}
