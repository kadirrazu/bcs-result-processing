<?php

namespace App\Reports\Themes;

use InvalidArgumentException;

/** Resolve named report themes from the central reports configuration. */
final class ReportThemeManager
{
    public function default(): ReportTheme
    {
        return $this->resolve((string) config('reports.default_theme', 'government'));
    }

    public function resolve(string $name): ReportTheme
    {
        $settings = config("reports.themes.{$name}");

        if (! is_array($settings)) {
            throw new InvalidArgumentException("Report theme [{$name}] is not configured.");
        }

        return new ReportTheme($name, $settings);
    }
}
