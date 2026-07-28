<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bangla PDF font
    |--------------------------------------------------------------------------
    |
    | Use a Unicode OpenType font containing Bengali GSUB/GPOS tables.
    | Legacy ANSI Nikosh files are rejected automatically because they cannot
    | shape Unicode Bengali text correctly. Forward slashes are recommended
    | for Windows paths in .env.
    |
    */
    'bangla_font_path' => env(
        'MASTER_DATA_PDF_BANGLA_FONT_PATH',
        env('MASTER_DATA_PDF_NIKOSH_PATH')
    ),

    'bangla_font_family' => env('MASTER_DATA_PDF_BANGLA_FONT_FAMILY', 'banglareport'),

    /* mPDF writes compiled font metrics and other temporary files here. */
    'temp_path' => env('MASTER_DATA_PDF_TEMP_PATH', storage_path('app/private/mpdf')),
];
