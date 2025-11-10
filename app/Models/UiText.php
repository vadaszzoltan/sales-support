<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UiText extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'key',
        'value_en',
        'value_ro',
        'value_hu',
        'description',
    ];

    /**
     * Get the translation value for a specific locale.
     * 
     * @param string $locale The locale code (en, ro, hu)
     * @return string|null The translation value or null if not set
     */
    public function getValue(string $locale): ?string
    {
        $column = "value_{$locale}";
        return $this->$column;
    }

    /**
     * Set the translation value for a specific locale.
     * 
     * @param string $locale The locale code (en, ro, hu)
     * @param string $value The translation value
     * @return void
     */
    public function setValue(string $locale, string $value): void
    {
        $column = "value_{$locale}";
        $this->$column = $value;
    }
}
