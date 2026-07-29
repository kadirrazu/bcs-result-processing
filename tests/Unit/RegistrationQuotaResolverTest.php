<?php

namespace Tests\Unit;

use App\Services\Registrations\RegistrationQuotaResolver;
use PHPUnit\Framework\TestCase;

/** Verify the authoritative raw-quota to derived-flag rule. */
final class RegistrationQuotaResolverTest extends TestCase
{
    public function test_only_ff_code_two_qualifies_for_ff_quota(): void
    {
        $resolver = new RegistrationQuotaResolver();
        self::assertFalse($resolver->hasQuota(1, null, null));
        self::assertTrue($resolver->hasQuota(2, null, null));
    }

    public function test_any_non_null_em_or_phc_code_qualifies(): void
    {
        $resolver = new RegistrationQuotaResolver();
        self::assertTrue($resolver->hasQuota(null, 0, null));
        self::assertTrue($resolver->hasQuota(null, null, 4));
        self::assertFalse($resolver->hasQuota(null, null, null));
    }
}
