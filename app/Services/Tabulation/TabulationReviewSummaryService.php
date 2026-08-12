<?php

namespace App\Services\Tabulation;

use App\Models\TabulationProcessingRun;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class TabulationReviewSummaryService
{
    public function forRun(TabulationProcessingRun $run): array
    {
        $base = DB::connection('exam')
            ->table('tabulation_results')
            ->where('processing_run_id', $run->id);

        $trackCounts = (clone $base)
            ->selectRaw('written_qualified_track, COUNT(*) as aggregate')
            ->groupBy('written_qualified_track')
            ->pluck('aggregate', 'written_qualified_track')
            ->map(fn ($value) => (int) $value)
            ->all();

        $vivaCounts = DB::connection('exam')
            ->table('tabulation_results as t')
            ->join('viva_results as v', 'v.id', '=', 't.viva_result_id')
            ->where('t.processing_run_id', $run->id)
            ->selectRaw('v.viva_result_status, COUNT(*) as aggregate')
            ->groupBy('v.viva_result_status')
            ->pluck('aggregate', 'v.viva_result_status')
            ->map(fn ($value) => (int) $value)
            ->all();

        return [
            'total' => (int) (clone $base)->count(),
            'valid' => $this->countStatus($base, 'valid'),
            'warning' => $this->countStatus($base, 'warning'),
            'error' => $this->countStatus($base, 'error'),
            'tracks' => [
                'GG' => (int) ($trackCounts['GG'] ?? 0),
                'GN' => (int) ($trackCounts['GN'] ?? 0),
                'TT' => (int) ($trackCounts['TT'] ?? 0),
                'T' => (int) ($trackCounts['T'] ?? 0),
                'GT' => (int) ($trackCounts['GT'] ?? 0),
            ],
            'general_merit_eligible' => (int) (clone $base)->where('general_merit_eligible', true)->count(),
            'technical_merit_eligible' => (int) (clone $base)->where('technical_merit_eligible', true)->count(),
            'both_merit_eligible' => (int) (clone $base)->where('general_merit_eligible', true)->where('technical_merit_eligible', true)->count(),
            'general_only_merit_eligible' => (int) (clone $base)->where('general_merit_eligible', true)->where('technical_merit_eligible', false)->count(),
            'technical_only_merit_eligible' => (int) (clone $base)->where('general_merit_eligible', false)->where('technical_merit_eligible', true)->count(),
            'not_merit_eligible' => (int) (clone $base)->where('general_merit_eligible', false)->where('technical_merit_eligible', false)->count(),
            'general_high_warning' => $this->countWarning($base, 'GENERAL_GRAND_TOTAL_HIGH_REVIEW'),
            'technical_high_warning' => $this->countWarning($base, 'TECHNICAL_GRAND_TOTAL_HIGH_REVIEW'),
            'viva_pass' => (int) ($vivaCounts['pass'] ?? 0),
            'viva_fail' => (int) ($vivaCounts['fail'] ?? 0),
        ];
    }

    private function countStatus(Builder $base, string $status): int
    {
        return (int) (clone $base)->where('validation_status', $status)->count();
    }

    private function countWarning(Builder $base, string $warning): int
    {
        return (int) (clone $base)->whereJsonContains('review_warnings', $warning)->count();
    }
}
