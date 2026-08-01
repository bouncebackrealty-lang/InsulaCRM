<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Headless browser PDF renderer
    |--------------------------------------------------------------------------
    |
    | Chrome/Chromium renders the exact same CSS used by the print preview.
    | Set PDF_BROWSER_PATH on servers where the browser is not installed at a
    | standard system location.
    |
    */
    'browser_path' => env('PDF_BROWSER_PATH'),

    'browser_timeout' => (int) env('PDF_BROWSER_TIMEOUT', 60),
];
