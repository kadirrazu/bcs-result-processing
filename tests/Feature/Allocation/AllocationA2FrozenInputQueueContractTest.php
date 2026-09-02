<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA2FrozenInputQueueContractTest extends TestCase
{
    #[Test]
    public function a2_schema_freeze_integrity_and_deterministic_queue_contract_is_installed(): void
    {
        $root = base_path();
        $migration = file_get_contents($root.'/database/examination-migrations/2026_09_02_214500_create_allocation_input_freeze_and_queues.php');
        $service = file_get_contents($root.'/app/Services/Allocation/AllocationInputFreezeService.php');
        $readiness = file_get_contents($root.'/app/Services/Allocation/AllocationReadinessService.php');
        $routes = file_get_contents($root.'/routes/allocation.php');
        $view = file_get_contents($root.'/resources/views/allocation/index.blade.php');
        $reset = file_get_contents($root.'/config/development-module-reset.php');

        foreach (['allocation_input_freezes', 'allocation_input_candidates', 'allocation_input_queue_entries'] as $table) {
            self::assertStringContainsString("->create('{$table}'", $migration);
            self::assertStringContainsString("'{$table}'", $reset);
        }

        // Immutable direct-input provenance + deterministic derived queue hash.
        foreach (['registration_hash', 'circular_hash', 'choice_hash', 'merit_hash', 'settings_hash', 'seat_breakup_hash', 'input_fingerprint', 'queue_hash'] as $field) {
            self::assertStringContainsString("'{$field}'", $service);
        }
        self::assertStringContainsString('DIRECT_INPUT_CHANGED_DURING_FREEZE', $service);
        self::assertStringContainsString('REGISTRATION_INPUT_HASH_MISMATCH', $service);
        self::assertStringContainsString('ALLOCATION_QUEUE_HASH_MISMATCH', $service);

        // Allocation-ready Choice source is explicit and optimization OFF falls back to finalized CV.
        self::assertStringContainsString("'choice_optimization'", $service);
        self::assertStringContainsString("'choice_validation'", $service);
        self::assertStringContainsString('final_choice_codes', $service);
        self::assertStringContainsString('validated_choice_codes', $service);

        // Queue membership is choice-only and target merit is authoritative.
        self::assertStringContainsString("'NO_ALLOCATION_READY_CHOICE'", $service);
        self::assertStringContainsString("'GENERAL_MERIT'", $service);
        self::assertStringContainsString("'TECHNICAL_CADRE_MERIT'", $service);
        self::assertStringContainsString('MeritCadreRank::query()', $service);
        self::assertStringContainsString("['cadre_code'], \$a['merit_position'], \$a['choice_position'], \$a['registration_id']", $service);

        // Quota entitlement is frozen separately from the future A3 allocation decision.
        self::assertStringContainsString("'CFF' => (bool) \$registration->has_ff_quota", $service);
        self::assertStringContainsString("'EM' => (bool) \$registration->has_em_quota", $service);
        self::assertStringContainsString("'PHC' => (bool) \$registration->has_phc_quota", $service);

        // A2 turns the former permanent PENDING readiness card into a real verified freeze gate.
        self::assertStringContainsString('storedCurrentSummary()', $readiness);
        self::assertStringContainsString('verifiedCurrent()', $readiness);
        self::assertStringContainsString("name('input-freeze.freeze')", $routes);
        self::assertStringContainsString('Freeze Direct Inputs & Build Queues', $view);
        self::assertStringContainsString('No allocation decision is made in this step', $view);
    }
}
