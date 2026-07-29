<?php

namespace Tests\Unit;

use App\Enums\CadreCategory;
use PHPUnit\Framework\TestCase;

/**
 * Lock the numeric source contract used by every processing stage.
 */
final class CadreCategoryTest extends TestCase
{
    public function test_numeric_values_map_to_processing_codes(): void
    {
        $this->assertSame('GG', CadreCategory::from(1)->code());
        $this->assertSame('TT', CadreCategory::from(2)->code());
        $this->assertSame('GT', CadreCategory::from(3)->code());
        $this->assertSame([1, 2, 3], CadreCategory::values());
    }
}
