<?php

namespace App\Services\Tabulation;

use App\Models\TabulationProcessingRun;
use Illuminate\Support\Facades\DB;

final class TabulationReviewSummaryService
{
    public function forRun(TabulationProcessingRun $run): array
    {
        $aggregate = DB::connection('exam')
            ->table('tabulation_results as t')
            ->join('viva_results as v', 'v.id', '=', 't.viva_result_id')
            ->where('t.processing_run_id', $run->id)
            ->selectRaw(<<<'SQL'
                COUNT(*) as total,
                SUM(CASE WHEN t.validation_status = 'valid' THEN 1 ELSE 0 END) as valid_count,
                SUM(CASE WHEN t.validation_status = 'warning' THEN 1 ELSE 0 END) as warning_count,
                SUM(CASE WHEN t.validation_status = 'error' THEN 1 ELSE 0 END) as error_count,
                SUM(CASE WHEN t.general_merit_eligible = 1 THEN 1 ELSE 0 END) as general_merit_eligible,
                SUM(CASE WHEN t.technical_merit_eligible = 1 THEN 1 ELSE 0 END) as technical_merit_eligible,
                SUM(CASE WHEN t.general_merit_eligible = 1 AND t.technical_merit_eligible = 1 THEN 1 ELSE 0 END) as both_merit_eligible,
                SUM(CASE WHEN t.general_merit_eligible = 1 AND t.technical_merit_eligible = 0 THEN 1 ELSE 0 END) as general_only_merit_eligible,
                SUM(CASE WHEN t.general_merit_eligible = 0 AND t.technical_merit_eligible = 1 THEN 1 ELSE 0 END) as technical_only_merit_eligible,
                SUM(CASE WHEN t.general_merit_eligible = 0 AND t.technical_merit_eligible = 0 THEN 1 ELSE 0 END) as not_merit_eligible,
                SUM(CASE WHEN v.viva_result_status = 'pass' THEN 1 ELSE 0 END) as viva_pass,
                SUM(CASE WHEN v.viva_result_status = 'fail' THEN 1 ELSE 0 END) as viva_fail,
                SUM(CASE WHEN JSON_CONTAINS(COALESCE(t.review_warnings, JSON_ARRAY()), JSON_QUOTE('GENERAL_GRAND_TOTAL_HIGH_REVIEW')) THEN 1 ELSE 0 END) as general_high_warning,
                SUM(CASE WHEN JSON_CONTAINS(COALESCE(t.review_warnings, JSON_ARRAY()), JSON_QUOTE('TECHNICAL_GRAND_TOTAL_HIGH_REVIEW')) THEN 1 ELSE 0 END) as technical_high_warning
            SQL)
            ->first();

        $trackCounts = DB::connection('exam')
            ->table('tabulation_results')
            ->where('processing_run_id', $run->id)
            ->selectRaw('written_qualified_track, COUNT(*) as aggregate')
            ->groupBy('written_qualified_track')
            ->pluck('aggregate', 'written_qualified_track')
            ->map(fn ($value) => (int) $value)
            ->all();

        $generalOnlyTrackPopulation = (int) ($trackCounts['GG'] ?? 0) + (int) ($trackCounts['GN'] ?? 0);
        $technicalOnlyTrackPopulation = (int) ($trackCounts['TT'] ?? 0) + (int) ($trackCounts['T'] ?? 0);
        $bothTrackPopulation = (int) ($trackCounts['GT'] ?? 0);

        $generalOnlyMeritEligible = (int) ($aggregate->general_only_merit_eligible ?? 0);
        $technicalOnlyMeritEligible = (int) ($aggregate->technical_only_merit_eligible ?? 0);
        $bothMeritEligible = (int) ($aggregate->both_merit_eligible ?? 0);

        return [
            'total' => (int) ($aggregate->total ?? 0),
            'valid' => (int) ($aggregate->valid_count ?? 0),
            'warning' => (int) ($aggregate->warning_count ?? 0),
            'error' => (int) ($aggregate->error_count ?? 0),
            'tracks' => [
                'GG' => (int) ($trackCounts['GG'] ?? 0),
                'GN' => (int) ($trackCounts['GN'] ?? 0),
                'TT' => (int) ($trackCounts['TT'] ?? 0),
                'T' => (int) ($trackCounts['T'] ?? 0),
                'GT' => (int) ($trackCounts['GT'] ?? 0),
            ],
            'general_merit_eligible' => (int) ($aggregate->general_merit_eligible ?? 0),
            'technical_merit_eligible' => (int) ($aggregate->technical_merit_eligible ?? 0),
            'both_merit_eligible' => $bothMeritEligible,
            'general_only_merit_eligible' => $generalOnlyMeritEligible,
            'technical_only_merit_eligible' => $technicalOnlyMeritEligible,
            'general_only_track_population' => $generalOnlyTrackPopulation,
            'technical_only_track_population' => $technicalOnlyTrackPopulation,
            'both_track_population' => $bothTrackPopulation,
            'general_only_not_merit_eligible' => max(0, $generalOnlyTrackPopulation - $generalOnlyMeritEligible),
            'technical_only_not_merit_eligible' => max(0, $technicalOnlyTrackPopulation - $technicalOnlyMeritEligible),
            'both_not_merit_eligible' => max(0, $bothTrackPopulation - $bothMeritEligible),
            'not_merit_eligible' => (int) ($aggregate->not_merit_eligible ?? 0),
            'general_high_warning' => (int) ($aggregate->general_high_warning ?? 0),
            'technical_high_warning' => (int) ($aggregate->technical_high_warning ?? 0),
            'viva_pass' => (int) ($aggregate->viva_pass ?? 0),
            'viva_fail' => (int) ($aggregate->viva_fail ?? 0),
        ];
    }
}
