<?php

namespace Tests\Unit;

use App\Services\Registrations\RegistrationBusinessRuleNormalizer;
use App\Services\Registrations\RegistrationQuotaResolver;
use App\Services\Registrations\RegistrationRowNormalizer;
use PHPUnit\Framework\TestCase;

/** Protect quota derivation and nullable fields during imports. */
final class RegistrationRowNormalizerTest extends TestCase
{
    private function normalizer(): RegistrationRowNormalizer
    {
        return new RegistrationRowNormalizer(
            new RegistrationBusinessRuleNormalizer(),
            new RegistrationQuotaResolver(),
        );
    }

    public function test_quota_flag_is_derived_from_raw_source_codes(): void
    {
        $result = $this->normalizer()->normalize([
            'user' => 'U1', 'reg' => '1', 'name' => 'A', 'cadre_category' => 1,
            'has_ff_quota' => 2, 'name_bn' => '',
        ], 9);
        $row = $result['attributes'];

        $this->assertTrue($row['has_quota']);
        $this->assertSame(2, $row['has_ff_quota']);
        $this->assertNull($row['name_bn']);
    }

    public function test_university_is_optional_and_source_division_is_ignored(): void
    {
        $result = $this->normalizer()->normalize([
            'user' => 'U1', 'reg' => '1', 'name' => 'A',
            'cadre_category' => 1, 'district' => 10, 'division' => 99, 'university' => '',
        ], 9);

        $this->assertNull($result['attributes']['university_code']);
        $this->assertNull($result['attributes']['division_code']);
    }

    public function test_non_qualifying_ff_code_without_other_quota_is_not_quota(): void
    {
        $result = $this->normalizer()->normalize([
            'user' => 'U1', 'reg' => '1', 'name' => 'A',
            'cadre_category' => 1, 'has_ff_quota' => 1,
        ], 9);

        $this->assertFalse($result['attributes']['has_quota']);
    }
}
