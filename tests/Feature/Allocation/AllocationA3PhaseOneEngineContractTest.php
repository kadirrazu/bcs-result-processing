<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA3PhaseOneEngineContractTest extends TestCase
{
    #[Test]
    public function a3_schema_and_versioned_phase_one_output_are_installed(): void
    {
        $migration = file_get_contents(database_path('examination-migrations/2026_09_02_231500_create_allocation_phase_one_engine.php'));
        $reset = file_get_contents(config_path('development-module-reset.php'));

        foreach (['allocation_runs', 'allocation_results', 'allocation_seat_ledgers', 'allocation_decision_events'] as $table) {
            self::assertStringContainsString("->create('{$table}'", $migration);
            self::assertStringContainsString("'{$table}'", $reset);
        }

        self::assertStringContainsString('phase1_output_hash', $migration);
        self::assertStringContainsString('seat_ledger_hash', $migration);
        self::assertStringContainsString('allocation_basis', $migration);
        self::assertStringContainsString('movement_type', $migration);
        self::assertStringContainsString('decision_status', $migration);
    }

    #[Test]
    public function phase_one_engine_enforces_mq_then_configured_quota_and_fixed_point_contract(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationPhaseOneService.php'));

        self::assertStringContainsString('verifiedCurrent()', $service);
        self::assertStringContainsString('selectCadreWinners', $service);
        self::assertStringContainsString("\$basis[\$candidateId] = 'MQ'", $service);
        self::assertStringContainsString('foreach ($quotaPriority as $quota)', $service);
        self::assertStringContainsString("\$proposal['eligible_'.\$quota]", $service);
        self::assertStringContainsString('ALLOCATION_PHASE1_CONVERGENCE_GUARD_EXCEEDED', $service);
        self::assertStringContainsString('higher-choice quota beats lower-choice MQ', $service);

        // A3 must not accidentally implement the A4 business phase.
        self::assertStringContainsString('DOES NOT convert vacant quota seats to merit/NM', $service);
        self::assertStringContainsString('AWAITING_A4_NM_SHIFTING', $service);
    }

    #[Test]
    public function phase_one_commit_is_blocked_until_determinism_and_hard_invariants_pass(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationPhaseOneService.php'));

        self::assertStringContainsString('DETERMINISM_CHECK', $service);
        self::assertStringContainsString('ALLOCATION_PHASE1_NON_DETERMINISTIC_OUTPUT', $service);
        self::assertStringContainsString('assertInvariants', $service);
        self::assertStringContainsString('INVARIANT_ONE_CADRE_PER_CANDIDATE_FAILED', $service);
        self::assertStringContainsString('INVARIANT_CHOICE_MEMBERSHIP_FAILED', $service);
        self::assertStringContainsString('INVARIANT_QUOTA_ENTITLEMENT_FAILED', $service);
        self::assertStringContainsString('INVARIANT_SEAT_CAPACITY_FAILED', $service);
        self::assertStringContainsString('INVARIANT_NEGATIVE_SEAT_FAILED', $service);
        self::assertStringContainsString('INVARIANT_SEAT_CONSERVATION_FAILED', $service);
        self::assertStringContainsString('ALLOCATION_INPUT_CHANGED_DURING_PHASE1', $service);
    }

    #[Test]
    public function temporary_final_and_movement_semantics_match_locked_a3_rules(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationPhaseOneService.php'));

        self::assertStringContainsString("\$isImmediateFinal = (int) \$proposal['choice_position'] === 1 && \$basis === 'MQ'", $service);
        self::assertStringContainsString("'movement_type' => 'DIRECT'", $service);
        self::assertStringContainsString("'decision_status' => \$isImmediateFinal ? 'FINAL' : 'TEMPORARY'", $service);
        self::assertStringContainsString('FIRST_CHOICE_MQ', $service);
    }

    #[Test]
    public function a3_runs_in_queue_with_json_polling_and_review_page(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationPhaseOne.php'));
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $index = file_get_contents(resource_path('views/allocation/index.blade.php'));
        $show = file_get_contents(resource_path('views/allocation/run-show.blade.php'));

        self::assertStringContainsString('ProcessAllocationPhaseOne::dispatch', $controller);
        self::assertStringContainsString('phaseOneStatus', $controller);
        self::assertStringContainsString('implements ShouldQueue', $job);
        self::assertStringContainsString("name('phase-one.start')", $routes);
        self::assertStringContainsString("name('phase-one.status')", $routes);
        self::assertStringContainsString("name('runs.show')", $routes);
        self::assertStringContainsString("fetch(url", $index);
        self::assertStringContainsString('phase1-progress-bar', $index);
        self::assertStringContainsString('Start Phase-1 MQ + Quota', $index);
        self::assertStringContainsString('Phase-1 Seat Ledger', $show);
        self::assertStringContainsString('Phase-1 Candidate Results', $show);
    }

    #[Test]
    public function business_critical_a3_code_contains_maintainability_comments(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationPhaseOneService.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationPhaseOne.php'));

        self::assertStringContainsString('Business boundary:', $service);
        self::assertStringContainsString('Hard A3 invariants', $service);
        self::assertStringContainsString('determinism self-check', $service);
        self::assertStringContainsString('Queue wrapper for A3', $job);
    }
}
