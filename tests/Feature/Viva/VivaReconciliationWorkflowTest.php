<?php

namespace Tests\Feature\Viva;

use PHPUnit\Framework\TestCase;

final class VivaReconciliationWorkflowTest extends TestCase
{
    public function test_locked_v4_review_rules_are_present_in_service(): void
    {
        $service = file_get_contents(__DIR__.'/../../../app/Services/Viva/VivaReconciliationService.php');
        self::assertStringContainsString('registration_only', $service);
        self::assertStringContainsString('viva_only', $service);
        self::assertStringContainsString('highMarkReviewMark', $service);
        self::assertStringContainsString("invalid_flag", $service);
        self::assertStringContainsString("issue_flag", $service);
        self::assertStringNotContainsString("viva_result_status\' => \'pass", $service);
    }
}
