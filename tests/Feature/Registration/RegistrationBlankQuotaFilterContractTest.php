<?php

namespace Tests\Feature\Registration;

use PHPUnit\Framework\TestCase;

class RegistrationBlankQuotaFilterContractTest extends TestCase
{
    public function test_blank_or_null_quota_filter_does_not_become_no_quota_filter(): void
    {
        $model = file_get_contents(app_path('Models/Registration.php'));

        // Laravel converts an empty query-string value to null. The filter must
        // therefore require a non-null value while still allowing the string "0".
        $this->assertStringContainsString(
            "isset(\$filters['has_quota']) && \$filters['has_quota'] !== ''",
            $model,
        );

        $this->assertStringNotContainsString(
            "array_key_exists('has_quota', \$filters) && \$filters['has_quota'] !== ''",
            $model,
        );
    }
}
