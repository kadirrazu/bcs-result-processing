<?php

namespace Tests\Unit;

use App\Services\Registrations\RegistrationBusinessRuleNormalizer;
use PHPUnit\Framework\TestCase;

final class RegistrationBusinessRuleNormalizerTest extends TestCase
{
    public function test_gg_post_related_subject_is_removed_and_reported(): void
    {
        $result = (new RegistrationBusinessRuleNormalizer())->normalize([
            'cadre_category' => 1,
            'post_related_subject_code' => 201,
        ]);

        $this->assertNull($result['attributes']['post_related_subject_code']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_technical_post_related_subject_is_preserved(): void
    {
        $result = (new RegistrationBusinessRuleNormalizer())->normalize([
            'cadre_category' => 2,
            'post_related_subject_code' => 201,
        ]);

        $this->assertSame(201, $result['attributes']['post_related_subject_code']);
        $this->assertSame([], $result['warnings']);
    }
}
