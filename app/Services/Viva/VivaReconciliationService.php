<?php

namespace App\Services\Viva;

use App\Models\VivaProcessingState;
use App\Models\VivaReconciliationRun;
use Illuminate\Support\Facades\DB;
use Throwable;

final class VivaReconciliationService
{
    private const REVIEW_CHUNK = 500;

    public function __construct(private readonly VivaRuleConfig $rules) {}

    public function process(int $runId, int $actorId): VivaReconciliationRun
    {
        $run = VivaReconciliationRun::query()->findOrFail($runId);
        $total = (int) DB::connection('exam')->table('viva_results')->count();

        $run->update([
            'status' => 'running',
            'total_candidates' => $total,
            'processed_candidates' => 0,
            'progress_percent' => 0,
            'started_at' => now(),
            'failure_message' => null,
        ]);

        VivaProcessingState::query()->updateOrCreate(['id' => 1], [
            'status' => 'reconciliation_running',
            'latest_reconciliation_run_id' => $run->id,
            'is_stale' => false,
            'stale_reason' => null,
        ]);

        try {
            $done = 0;
            DB::connection('exam')->table('viva_results as v')
                ->join('registrations as r', 'r.id', '=', 'v.registration_id')
                ->select('v.*', 'r.has_ff_quota', 'r.has_em_quota', 'r.has_phc_quota')
                ->orderBy('v.id')
                ->chunkById(self::REVIEW_CHUNK, function ($rows) use ($run, $total, &$done): void {
                    $payload = [];
                    foreach ($rows as $row) {
                        $mismatches = [];
                        $appeared = strtolower((string) $row->attendance_status) === 'appeared';

                        // Viva Board quota certification is meaningful only for candidates
                        // who actually appeared before the Viva Board. ABS candidates are
                        // therefore excluded from Registration ↔ Viva quota comparison.
                        if ($appeared) {
                            foreach ([
                                'CFF' => ['registration' => $row->has_ff_quota, 'viva' => $row->viva_cff],
                                'EM' => ['registration' => $row->has_em_quota, 'viva' => $row->viva_em],
                                'PHC' => ['registration' => $row->has_phc_quota, 'viva' => $row->viva_phc],
                            ] as $quota => $values) {
                                $registration = (int) $values['registration'] > 0;
                                $viva = (bool) $values['viva'];

                                if ($registration !== $viva) {
                                    $mismatches[$quota] = [
                                        'registration' => $registration,
                                        'viva' => $viva,
                                        'direction' => $registration ? 'registration_only' : 'viva_only',
                                    ];
                                }
                            }
                        }

                        $highMark = $appeared
                            && $row->mark !== null
                            && (float) $row->mark >= $this->rules->highMarkReviewMark();

                        $warning = (bool) $row->invalid_flag
                            || (bool) $row->issue_flag
                            || $mismatches !== []
                            || $highMark;

                        $full = (array) $row;
                        unset($full['has_ff_quota'], $full['has_em_quota'], $full['has_phc_quota']);
                        $full['quota_mismatch'] = $mismatches !== [] ? 1 : 0;
                        $full['quota_mismatch_details'] = $mismatches === [] ? null : json_encode($mismatches, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $full['high_mark_review'] = $highMark ? 1 : 0;
                        $full['validation_status'] = $warning ? 'warning' : 'valid';
                        $full['updated_at'] = now();
                        $payload[] = $full;
                    }

                    if ($payload !== []) {
                        DB::connection('exam')->table('viva_results')->upsert(
                            $payload,
                            ['id'],
                            ['quota_mismatch', 'quota_mismatch_details', 'high_mark_review', 'validation_status', 'updated_at']
                        );
                    }

                    $done += count($payload);
                    $run->update([
                        'processed_candidates' => $done,
                        'progress_percent' => $total > 0 ? min(99.9, round(($done / $total) * 100, 4)) : 99.9,
                    ]);
                }, 'v.id', 'id');

            $summary = $this->buildSummary();
            $run->update([
                ...$summary,
                'status' => 'completed',
                'processed_candidates' => $total,
                'progress_percent' => 100,
                'finished_at' => now(),
            ]);

            VivaProcessingState::query()->updateOrCreate(['id' => 1], [
                'status' => 'reconciliation_generated',
                'latest_reconciliation_run_id' => $run->id,
                'reconciliation_generated_by' => $actorId,
                'reconciliation_generated_at' => now(),
                'summary' => $summary,
                'is_stale' => false,
                'stale_reason' => null,
            ]);

            return $run->refresh();
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'failure_message' => mb_substr($e->getMessage(), 0, 65000),
                'finished_at' => now(),
            ]);
            VivaProcessingState::query()->updateOrCreate(['id' => 1], [
                'status' => 'board_data_imported',
            ]);
            throw $e;
        }
    }

    private function buildSummary(): array
    {
        $exam = DB::connection('exam');
        $eligible = $exam->table('written_results')
            ->where('status', 'active')
            ->whereNotNull('written_qualified_track')
            ->whereNotNull('finalized_at')
            ->count();
        $mapped = $exam->table('viva_candidate_mappings')->count();
        $board = $exam->table('viva_results')->count();

        $base = $exam->table('viva_results');
        $quotaTypeCounts = [];
        foreach (['CFF', 'EM', 'PHC'] as $quota) {
            $quotaTypeCounts[$quota] = (int) $exam->table('viva_results')
                ->where('quota_mismatch', 1)
                ->whereNotNull('quota_mismatch_details')
                ->whereRaw("JSON_EXTRACT(quota_mismatch_details, '$.{$quota}') IS NOT NULL")
                ->count();
        }

        $trackSummary = $exam->table('written_results')
            ->where('status', 'active')->whereNotNull('written_qualified_track')->whereNotNull('finalized_at')
            ->selectRaw('written_qualified_track, COUNT(*) total')
            ->groupBy('written_qualified_track')->pluck('total', 'written_qualified_track')->map(fn ($v) => (int) $v)->all();

        $categorySummary = $exam->table('written_results')
            ->where('status', 'active')->whereNotNull('written_qualified_track')->whereNotNull('finalized_at')
            ->selectRaw('cadre_category, COUNT(*) total')
            ->groupBy('cadre_category')->pluck('total', 'cadre_category')->map(fn ($v) => (int) $v)->all();

        $directions = ['registration_only' => 0, 'viva_only' => 0];
        foreach (['CFF', 'EM', 'PHC'] as $quota) {
            foreach (array_keys($directions) as $direction) {
                $directions[$direction] += (int) $exam->table('viva_results')
                    ->where('quota_mismatch', 1)
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(quota_mismatch_details, '$.{$quota}.direction')) = ?", [$direction])
                    ->count();
            }
        }

        return [
            'eligible_count' => (int) $eligible,
            'mapped_count' => (int) $mapped,
            'board_data_count' => (int) $board,
            'missing_mapping_count' => max(0, (int) $eligible - (int) $mapped),
            'missing_board_count' => max(0, (int) $mapped - (int) $board),
            'appeared_count' => (int) (clone $base)->where('attendance_status', 'appeared')->count(),
            'absent_count' => (int) (clone $base)->where('attendance_status', 'absent')->count(),
            'active_count' => (int) (clone $base)->where('status', 'active')->count(),
            'cancelled_count' => (int) (clone $base)->where('status', 'cancelled')->count(),
            'withheld_count' => (int) (clone $base)->where('status', 'withheld')->count(),
            'expelled_count' => (int) (clone $base)->where('status', 'expelled')->count(),
            'warning_count' => (int) (clone $base)->where('validation_status', 'warning')->count(),
            'quota_mismatch_count' => (int) (clone $base)->where('quota_mismatch', 1)->count(),
            'quota_cff_mismatch_count' => $quotaTypeCounts['CFF'],
            'quota_em_mismatch_count' => $quotaTypeCounts['EM'],
            'quota_phc_mismatch_count' => $quotaTypeCounts['PHC'],
            'source_invalid_count' => (int) (clone $base)->where('invalid_flag', 1)->count(),
            'source_issue_count' => (int) (clone $base)->where('issue_flag', 1)->count(),
            'high_mark_count' => (int) (clone $base)->where('high_mark_review', 1)->count(),
            'track_summary' => $trackSummary,
            'category_summary' => $categorySummary,
            'review_summary' => [
                'quota_direction_counts' => $directions,
                'high_mark_threshold_percent' => $this->rules->highMarkReviewPercent(),
                'high_mark_threshold_mark' => $this->rules->highMarkReviewMark(),
            ],
        ];
    }
}
