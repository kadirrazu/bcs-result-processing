<?php

namespace App\Services\Preliminary;

use App\Enums\PreliminaryProcessingStatus;
use App\Models\PreliminaryDistributionReport;
use App\Models\PreliminaryProcessingState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PreliminaryDistributionService
{
    /** @return array{report:PreliminaryDistributionReport,summary:array<string,mixed>} */
    public function generate(int $actorId): array
    {
        $state = PreliminaryProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => PreliminaryProcessingStatus::NotStarted->value],
        );

        if ($state->latest_import_batch_id === null) {
            throw ValidationException::withMessages(['distribution' => 'Approve preliminary marks before generating distribution.']);
        }

        if ($state->latest_reconciliation_report_id === null || $state->reconciliation_generated_at === null) {
            throw ValidationException::withMessages(['distribution' => 'Generate the latest Present / Absent report before mark distribution.']);
        }

        $rows = DB::connection('exam')->table('preliminary_results as p')
            ->join('registrations as r', 'r.id', '=', 'p.registration_id')
            ->where('r.status', 'active')
            ->where('p.candidate_status', 'active')
            ->whereNotNull('p.mark')
            ->selectRaw('p.mark, COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN r.cadre_category = 1 THEN 1 ELSE 0 END) as gg')
            ->selectRaw('SUM(CASE WHEN r.cadre_category = 2 THEN 1 ELSE 0 END) as tt')
            ->selectRaw('SUM(CASE WHEN r.cadre_category = 3 THEN 1 ELSE 0 END) as gt')
            ->groupBy('p.mark')
            ->orderByDesc('p.mark')
            ->get();

        $cumulative = ['total' => 0, 'GG' => 0, 'TT' => 0, 'GT' => 0];
        $distribution = [];

        foreach ($rows as $row) {
            $count = [
                'total' => (int) $row->total,
                'GG' => (int) $row->gg,
                'TT' => (int) $row->tt,
                'GT' => (int) $row->gt,
            ];

            foreach ($cumulative as $key => $value) {
                $cumulative[$key] = $value + $count[$key];
            }

            $distribution[] = [
                'mark' => number_format((float) $row->mark, 2, '.', ''),
                'count' => $count,
                'cumulative' => $cumulative,
            ];
        }

        $summary = [
            'eligible_candidates' => $cumulative['total'],
            'GG' => $cumulative['GG'],
            'TT' => $cumulative['TT'],
            'GT' => $cumulative['GT'],
            'distinct_marks' => count($distribution),
            'maximum_mark' => $distribution[0]['mark'] ?? null,
            'minimum_mark' => $distribution !== [] ? $distribution[array_key_last($distribution)]['mark'] : null,
        ];

        $report = PreliminaryDistributionReport::query()->create([
            'import_batch_id' => $state->latest_import_batch_id,
            'reconciliation_report_id' => $state->latest_reconciliation_report_id,
            'eligible_candidates' => $summary['eligible_candidates'],
            'gg_candidates' => $summary['GG'],
            'tt_candidates' => $summary['TT'],
            'gt_candidates' => $summary['GT'],
            'distinct_marks' => $summary['distinct_marks'],
            'minimum_mark' => $summary['minimum_mark'],
            'maximum_mark' => $summary['maximum_mark'],
            'distribution' => $distribution,
            'generated_by' => $actorId,
            'generated_at' => now(),
        ]);

        $existingSummary = is_array($state->summary) ? $state->summary : [];
        $state->update([
            'status' => $state->cutoff_mark !== null
                ? PreliminaryProcessingStatus::CutoffSet->value
                : PreliminaryProcessingStatus::DistributionGenerated->value,
            'latest_distribution_report_id' => $report->id,
            'distribution_generated_by' => $actorId,
            'distribution_generated_at' => now(),
            'result_finalized_by' => null,
            'result_finalized_at' => null,
            'summary' => [...$existingSummary, 'distribution' => $summary],
        ]);

        return ['report' => $report, 'summary' => $summary];
    }
}
