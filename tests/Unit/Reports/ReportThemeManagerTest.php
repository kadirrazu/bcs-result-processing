<?php

namespace Tests\Unit\Reports;

use App\Reports\Themes\ReportThemeManager;
use InvalidArgumentException;
use Tests\TestCase;

final class ReportThemeManagerTest extends TestCase
{
    public function test_it_resolves_the_default_report_theme(): void
    {
        $theme = app(ReportThemeManager::class)->default();

        $this->assertSame('government', $theme->name);
        $this->assertSame(12.0, $theme->number('fonts.english_size_pt'));
        $this->assertSame(13.0, $theme->number('fonts.bangla_size_pt'));
        $this->assertSame(29.0, $theme->number('page.margin_top_mm'));
    }

    public function test_it_rejects_an_unknown_theme(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ReportThemeManager::class)->resolve('missing-theme');
    }
}
