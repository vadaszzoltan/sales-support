<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'approved',
        'locale', // User preferred UI language (en, ro, hu)
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'approved' => 'boolean',
        ];
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is sales agent
     */
    public function isSalesAgent(): bool
    {
        return $this->role === 'sales_agent';
    }

    /**
     * Filament access control - only approved admins can access Filament
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->approved;
    }

    /**
     * A user can create multiple quotes
     */
    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Get the user's preferred locale.
     * Returns the user's locale preference, or falls back to default locale from config.
     * 
     * @return string The locale code (en, ro, or hu)
     */
    public function getPreferredLocale(): string
    {
        // Validate locale is supported
        $supportedLocales = config('locales.supported', ['en', 'ro', 'hu']);
        
        if ($this->locale && in_array($this->locale, $supportedLocales)) {
            return $this->locale;
        }
        
        // If user has no locale set, use UI default (which is 'hu' by default)
        return config('locales.ui_default', config('locales.default', 'en'));
    }
}

