<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Light Mode Accent
    |--------------------------------------------------------------------------
    |
    | These variables override the Flux/Tailwind --color-accent CSS variables
    | for the :root (light mode) context. Defaults match the current yellow
    | accent defined in resources/css/app.css.
    |
    */

    'accent' => env('THEME_ACCENT', 'var(--color-yellow-400)'),
    'accent_content' => env('THEME_ACCENT_CONTENT', 'var(--color-yellow-600)'),
    'accent_foreground' => env('THEME_ACCENT_FOREGROUND', 'var(--color-yellow-950)'),

    /*
    |--------------------------------------------------------------------------
    | Dark Mode Accent
    |--------------------------------------------------------------------------
    |
    | These variables override the --color-accent CSS variables under the
    | .dark class context. Defaults match the app.css dark-mode values.
    |
    */

    'dark_accent' => env('THEME_DARK_ACCENT', 'var(--color-yellow-400)'),
    'dark_accent_content' => env('THEME_DARK_ACCENT_CONTENT', 'var(--color-yellow-400)'),
    'dark_accent_foreground' => env('THEME_DARK_ACCENT_FOREGROUND', 'var(--color-yellow-950)'),

    /*
    |--------------------------------------------------------------------------
    | Branding Icons
    |--------------------------------------------------------------------------
    |
    | Per-environment icon paths relative to the public directory. These files
    | should NOT be committed to version control (see .gitignore) so each
    | deployment can place its own branding assets.
    |
    */

    'favicon_ico' => env('THEME_FAVICON_ICO', '/favicon.ico'),
    'favicon_svg' => env('THEME_FAVICON_SVG', '/favicon.svg'),
    'apple_touch_icon' => env('THEME_APPLE_ICON', '/apple-touch-icon.png'),

];
