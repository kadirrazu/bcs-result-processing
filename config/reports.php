<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default PDF report theme
    |--------------------------------------------------------------------------
    |
    | All measurements used by mPDF are millimetres unless the key explicitly
    | uses the "_pt" suffix. Future reports should resolve their appearance
    | through ReportThemeManager rather than embedding typography in classes.
    |
    */
    'default_theme' => env('REPORT_DEFAULT_THEME', 'government'),

    'themes' => [
        'government' => [
            'fonts' => [
                'english_family' => 'dejavusans',
                'english_size_pt' => 12,
                'bangla_size_pt' => 13,
                'title_size_pt' => 15,
                'meta_size_pt' => 9,
                'table_header_size_pt' => 11,
                'footer_size_pt' => 8,
            ],

            'page' => [
                'margin_left_mm' => 12.7,
                'margin_right_mm' => 12.7,
                // Reduced from 34 mm to create a smaller header-to-table gap.
                'margin_top_mm' => 29,
                'margin_bottom_mm' => 18,
                'margin_header_mm' => 7,
                'margin_footer_mm' => 8,
            ],

            'table' => [
                'cell_padding_vertical_mm' => 1.8,
                'cell_padding_horizontal_mm' => 1.5,
                'body_line_height' => 1.25,
                'bangla_line_height' => 1.38,
                'header_line_height' => 1.18,
                'border_width_mm' => 0.25,
            ],

            'colors' => [
                'text' => '#182433',
                'muted' => '#68778a',
                'footer' => '#9aa6b5',
                'border' => '#7f8da0',
                'header_background' => '#e9eef5',
                'footer_border' => '#d7dde6',
            ],
        ],
    ],
];
