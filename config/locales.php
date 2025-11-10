<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | List of all supported locales in the application.
    | These are used for UI translations and product names.
    |
    */
    'supported' => ['en', 'ro', 'hu'],

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | The default locale used for base values in the database.
    | This is typically English (en) and serves as the fallback language.
    |
    */
    'default' => 'en',

    /*
    |--------------------------------------------------------------------------
    | UI Default Locale
    |--------------------------------------------------------------------------
    |
    | The default locale for the user interface.
    | This can be overridden per-user later.
    |
    */
    'ui_default' => 'hu',

    /*
    |--------------------------------------------------------------------------
    | PDF Locale
    |--------------------------------------------------------------------------
    |
    | The locale that must ALWAYS be used for PDF generation.
    | Quotes must always be generated in Romanian.
    |
    */
    'pdf_locale' => 'ro',
];

