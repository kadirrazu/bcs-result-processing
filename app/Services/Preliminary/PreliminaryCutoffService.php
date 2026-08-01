<?php

namespace App\Services\Preliminary;

use App\Enums\PreliminaryProcessingStatus;
use App\Models\PreliminaryCutoffDecision;
use App\Models\PreliminaryDistributionReport;
use App\Models\PreliminaryProcessingState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PreliminaryCutoffService
{
    public function __construct(private readonly PreliminaryAuditService $audit) {}

    public function propose(float $mark, string $reason, User $actor): PreliminaryCutoffDecision
    {
        $state = PreliminaryProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => PreliminaryProcessingStatus::NotStarted->value],
        );

        if ($state->latest_distribution_report_id === null || $state->distribution_generated_at === null) {
            throw ValidationException::withMessages(['cutoff_mark' => 'Generate a current mark distribution before proposing cut-off.']);
        }

        $report = PreliminaryDistributionReport::query()->findOrFail($state->latest_distribution_report_id);
        $counts = $this->passCounts($mark);
        $before = $this->stateSnapshot($state);

        $decision = DB::connection('exam')->transaction(function () use ($report, $mark, $reason, $actor, $counts): PreliminaryCutoffDecision {
            PreliminaryCutoffDecision::query()->where('status', 'proposed')->update(['status' => 'superseded']);

            return PreliminaryCutoffDecision::query()->create([
                'distribution_report_id' => $report->id,
                'cutoff_mark' => $mark,
                'status' => 'proposed',
                'reason' => $reason,
                'proposed_by' => $actor->id,
                'proposed_at' => now(),
                'pass_total' => $counts['total'],
                'pass_gg' => $counts['GG'],
                'pass_tt' => $counts['TT'],
                'pass_gt' => $counts['GT'],
                'snapshot' => [
                    'distribution_report_id' => $report->id,
                    'eligible_candidates' => $report->eligible_candidates,
                    'pass_counts' => $counts,
                ],
            ]);
        });

        $this->audit->record(
            'CUTOFF_PROPOSED', $actor,
            $this->stateStatus($state), $this->stateStatus($state),
            $reason,
            ['cutoff_mark' => number_format($mark, 2, '.', ''), 'pass_counts' => $counts],
            $before,
            ['decision_id' => $decision->id, 'cutoff_mark' => number_format($mark, 2, '.', ''), 'pass_counts' => $counts],
            batchId: $state->latest_import_batch_id,
            processingRunId: $decision->id,
        );

        return $decision;
    }

    public function approve(PreliminaryCutoffDecision $decision, string $reason, User $actor): PreliminaryCutoffDecision
    {
        if ($decision->status !== 'proposed') {
            throw ValidationException::withMessages(['approval_reason' => 'Only a proposed cut-off can be approved.']);
        }

        $state = PreliminaryProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => PreliminaryProcessingStatus::NotStarted->value],
        );

        if ((int) $state->latest_distribution_report_id !== (int) $decision->distribution_report_id) {
            throw ValidationException::withMessages(['approval_reason' => 'This proposal belongs to an outdated mark distribution. Generate/propose again.']);
        }

        $before = $this->stateSnapshot($state);
        $beforeStatus = $this->stateStatus($state);

        DB::connection('exam')->transaction(function () use ($decision, $reason, $actor, $state): void {
            PreliminaryCutoffDecision::query()
                ->where('status', 'approved')
                ->where('id', '!=', $decision->id)
                ->update(['status' => 'superseded']);

            $decision->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approval_reason' => $reason,
            ]);

            // A new/changed approved cut-off invalidates every previously derived PASS/FAIL fact.
            DB::connection('exam')->table('preliminary_results')->update([
                'result_status' => DB::raw("CASE WHEN candidate_status = 'cancelled' THEN 'cancelled' ELSE NULL END"),
                'applied_cutoff_mark' => null,
                'finalized_at' => null,
                'updated_at' => now(),
            ]);

            $existingSummary = is_array($state->summary) ? $state->summary : [];
            unset($existingSummary['finalization']);
            $state->update([
                'status' => PreliminaryProcessingStatus::CutoffSet->value,
                'current_cutoff_decision_id' => $decision->id,
                'latest_finalization_run_id' => null,
                'cutoff_mark' => $decision->cutoff_mark,
                'cutoff_set_by' => $actor->id,
                'cutoff_set_at' => now(),
                'cutoff_requires_review' => false,
                'result_finalized_by' => null,
                'result_finalized_at' => null,
                'summary' => [
                    ...$existingSummary,
                    'cutoff' => [
                        'decision_id' => $decision->id,
                        'mark' => $decision->cutoff_mark,
                        'pass_total' => $decision->pass_total,
                        'GG' => $decision->pass_gg,
                        'TT' => $decision->pass_tt,
                        'GT' => $decision->pass_gt,
                    ],
                ],
            ]);
        });

        $decision->refresh();
        $state->refresh();

        $this->audit->record(
            'CUTOFF_APPROVED', $actor,
            $beforeStatus, PreliminaryProcessingStatus::CutoffSet->value,
            $reason,
            [
                'decision_id' => $decision->id,
                'cutoff_mark' => $decision->cutoff_mark,
                'pass_total' => $decision->pass_total,
                'GG' => $decision->pass_gg,
                'TT' => $decision->pass_tt,
                'GT' => $decision->pass_gt,
            ],
            $before,
            $this->stateSnapshot($state),
            batchId: $state->latest_import_batch_id,
            processingRunId: $decision->id,
        );

        return $decision;
    }

    /** @return array{total:int,GG:int,TT:int,GT:int} */
    public function passCounts(float $mark): array
    {
        $rows = DB::connection('exam')->table('preliminary_results as p')
            ->join('registrations as r', 'r.id', '=', 'p.registration_id')
            ->where('r.status', 'active')
            ->where('p.candidate_status', 'active')
            ->whereNotNull('p.mark')
            ->where('p.mark', '>=', $mark)
            ->selectRaw('r.cadre_category, COUNT(*) as aggregate')
            ->groupBy('r.cadre_category')
            ->pluck('aggregate', 'r.cadre_category');

        $gg = (int) ($rows[1] ?? 0);
        $tt = (int) ($rows[2] ?? 0);
        $gt = (int) ($rows[3] ?? 0);

        return ['total' => $gg + $tt + $gt, 'GG' => $gg, 'TT' => $tt, 'GT' => $gt];
    }

    private function stateStatus(PreliminaryProcessingState $state): string
    {
        return $state->status instanceof \BackedEnum ? $state->status->value : (string) $state->status;
    }

    /** @return array<string,mixed> */
    private function stateSnapshot(PreliminaryProcessingState $state): array
    {
        return [
            'status' => $this->stateStatus($state),
            'latest_distribution_report_id' => $state->latest_distribution_report_id,
            'current_cutoff_decision_id' => $state->current_cutoff_decision_id,
            'cutoff_mark' => $state->cutoff_mark,
            'cutoff_set_by' => $state->cutoff_set_by,
            'cutoff_set_at' => optional($state->cutoff_set_at)->toDateTimeString(),
            'cutoff_requires_review' => (bool) $state->cutoff_requires_review,
        ];
    }
}
