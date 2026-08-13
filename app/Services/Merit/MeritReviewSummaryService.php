<?php

namespace App\Services\Merit;

use App\Models\MeritProcessingRun;
use Illuminate\Support\Facades\DB;

final class MeritReviewSummaryService
{
    /** @return array<string,int> */
    public function forRun(MeritProcessingRun $run): array
    {
        $row = DB::connection('exam')->table('merit_results')
            ->where('processing_run_id', $run->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(common_merit_position IS NOT NULL) as common_ranked')
            ->selectRaw('SUM(general_merit_position IS NOT NULL) as general_ranked')
            ->selectRaw('SUM(technical_merit_position IS NOT NULL) as technical_ranked')
            ->selectRaw('SUM(common_merit_position IS NULL) as common_not_ranked')
            ->selectRaw("SUM(status_reason = 'NOT_MERIT_ELIGIBLE') as not_merit_eligible")
            ->selectRaw("SUM(written_qualified_track = 'GG') as track_gg")
            ->selectRaw("SUM(written_qualified_track = 'GN') as track_gn")
            ->selectRaw("SUM(written_qualified_track = 'TT') as track_tt")
            ->selectRaw("SUM(written_qualified_track = 'T') as track_t")
            ->selectRaw("SUM(written_qualified_track = 'GT') as track_gt")
            ->first();

        $cadre = DB::connection('exam')->table('merit_cadre_ranks')
            ->where('processing_run_id', $run->id)
            ->selectRaw('COUNT(*) as cadre_rows, COUNT(DISTINCT cadre_code) as cadre_count')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'common_ranked' => (int) ($row->common_ranked ?? 0),
            'general_ranked' => (int) ($row->general_ranked ?? 0),
            'technical_ranked' => (int) ($row->technical_ranked ?? 0),
            'common_not_ranked' => (int) ($row->common_not_ranked ?? 0),
            'not_merit_eligible' => (int) ($row->not_merit_eligible ?? 0),
            'track_gg' => (int) ($row->track_gg ?? 0),
            'track_gn' => (int) ($row->track_gn ?? 0),
            'track_tt' => (int) ($row->track_tt ?? 0),
            'track_t' => (int) ($row->track_t ?? 0),
            'track_gt' => (int) ($row->track_gt ?? 0),
            'cadre_rows' => (int) ($cadre->cadre_rows ?? 0),
            'cadre_count' => (int) ($cadre->cadre_count ?? 0),
        ];
    }
}
