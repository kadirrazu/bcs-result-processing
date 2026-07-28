<?php

namespace App\Reports\Themes;

use InvalidArgumentException;

/** Immutable, validated presentation settings shared by PDF reports. */
final readonly class ReportTheme
{
    /** @param array<string, mixed> $settings */
    public function __construct(
        public string $name,
        private array $settings,
    ) {
        $this->assertRequiredSettings();
    }

    public function string(string $path): string
    {
        $value = data_get($this->settings, $path);

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("Report theme setting [{$path}] must be a non-empty string.");
        }

        return $value;
    }

    public function number(string $path): float
    {
        $value = data_get($this->settings, $path);

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Report theme setting [{$path}] must be numeric.");
        }

        return (float) $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->settings;
    }

    private function assertRequiredSettings(): void
    {
        $required = [
            'fonts.english_family',
            'fonts.english_size_pt',
            'fonts.bangla_size_pt',
            'fonts.title_size_pt',
            'fonts.meta_size_pt',
            'fonts.table_header_size_pt',
            'fonts.footer_size_pt',
            'page.margin_left_mm',
            'page.margin_right_mm',
            'page.margin_top_mm',
            'page.margin_bottom_mm',
            'page.margin_header_mm',
            'page.margin_footer_mm',
            'table.cell_padding_vertical_mm',
            'table.cell_padding_horizontal_mm',
            'table.body_line_height',
            'table.bangla_line_height',
            'table.header_line_height',
            'table.border_width_mm',
            'colors.text',
            'colors.muted',
            'colors.footer',
            'colors.border',
            'colors.header_background',
            'colors.footer_border',
        ];

        foreach ($required as $path) {
            if (data_get($this->settings, $path) === null) {
                throw new InvalidArgumentException("Report theme [{$this->name}] is missing [{$path}].");
            }
        }
    }
}
