<?php

namespace Tests\Unit;

use App\Services\Registrations\RegistrationUniversityCodePolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegistrationUniversityCodePolicyTest extends TestCase
{
    #[Test]
    public function it_preserves_an_unknown_code_and_records_a_non_blocking_warning(): void
    {
        $result = (new RegistrationUniversityCodePolicy())->apply([
            'university_code' => 999,
            'comment' => 'Source verified',
        ], ['100' => true]);

        $this->assertSame(999, $result['attributes']['university_code']);
        $this->assertSame(
            "Source verified\n[IMPORT WARNING] Invalid University Code: 999",
            $result['attributes']['comment'],
        );
        $this->assertSame(['[IMPORT WARNING] Invalid University Code: 999'], $result['warnings']);
    }

    #[Test]
    public function it_does_not_duplicate_the_same_warning(): void
    {
        $warning = '[IMPORT WARNING] Invalid University Code: 999';
        $result = (new RegistrationUniversityCodePolicy())->apply([
            'university_code' => 999,
            'comment' => $warning,
        ], []);

        $this->assertSame($warning, $result['attributes']['comment']);
    }

    #[Test]
    public function blank_or_known_codes_do_not_generate_warnings(): void
    {
        $policy = new RegistrationUniversityCodePolicy();

        $this->assertSame([], $policy->apply(['university_code' => null, 'comment' => null], [])['warnings']);
        $this->assertSame([], $policy->apply(['university_code' => 100, 'comment' => null], ['100' => true])['warnings']);
    }
}
