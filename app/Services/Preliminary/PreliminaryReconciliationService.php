<?php

namespace App\Services\Preliminary;

use App\Enums\PreliminaryProcessingStatus;
use App\Models\PreliminaryProcessingState;
use App\Models\PreliminaryReconciliationReport;
use Illuminate\Support\Facades\DB;

final class PreliminaryReconciliationService
{
    /** @return array{report:PreliminaryReconciliationReport,summary:array<string,mixed>} */
    public function generate(int $actorId): array
    {
        $state = PreliminaryProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => PreliminaryProcessingStatus::NotStarted->value],
        );

        $activeRegistered = DB::connection('exam')->table('registrations')->where('status', 'active')->count();
        $importedRows = DB::connection('exam')->table('preliminary_results')->count();

        $summary = [
            'active_registered' => $activeRegistered,
            'imported_rows' => $importedRows,
            'present_with_mark' => $this->countGroup('present_with_mark'),
            'present_with_status_text' => $this->countGroup('present_with_status_text'),
            'cancelled_with_reason' => $this->countGroup('cancelled_with_reason'),
            'cancelled_without_reason' => $this->countGroup('cancelled_without_reason'),
            'absent' => $this->countGroup('absent'),
            'excluded_non_active_registration' => DB::connection('exam')->table('preliminary_results as p')
                ->join('registrations as r', 'r.id', '=', 'p.registration_id')
                ->where('r.status', '!=', 'active')
                ->count(),
        ];

        $summary['category'] = [
            'active_registered' => $this->categoryBreakdown('active_registered'),
            'present_with_mark' => $this->categoryBreakdown('present_with_mark'),
            'present_with_status_text' => $this->categoryBreakdown('present_with_status_text'),
            'cancelled_with_reason' => $this->categoryBreakdown('cancelled_with_reason'),
            'cancelled_without_reason' => $this->categoryBreakdown('cancelled_without_reason'),
            'absent' => $this->categoryBreakdown('absent'),
        ];

        $report = PreliminaryReconciliationReport::query()->create([
            'import_batch_id' => $state->latest_import_batch_id,
            'active_registered' => $summary['active_registered'],
            'imported_rows' => $summary['imported_rows'],
            'present_with_mark' => $summary['present_with_mark'],
            'present_with_status_text' => $summary['present_with_status_text'],
            'cancelled_with_reason' => $summary['cancelled_with_reason'],
            'cancelled_without_reason' => $summary['cancelled_without_reason'],
            'absent' => $summary['absent'],
            'excluded_non_active_registration' => $summary['excluded_non_active_registration'],
            'summary' => $summary,
            'generated_by' => $actorId,
            'generated_at' => now(),
        ]);

        $existingSummary = is_array($state->summary) ? $state->summary : [];
        $state->update([
            'status' => PreliminaryProcessingStatus::ReconciliationGenerated->value,
            'latest_reconciliation_report_id' => $report->id,
            'reconciliation_generated_by' => $actorId,
            'reconciliation_generated_at' => now(),
            'summary' => [...$existingSummary, 'reconciliation' => $summary],
        ]);

        return ['report' => $report, 'summary' => $summary];
    }

    private function baseResultQuery()
    {
        return DB::connection('exam')->table('preliminary_results as p')
            ->join('registrations as r', 'r.id', '=', 'p.registration_id')
            ->where('r.status', 'active');
    }

    private function countGroup(string $group): int
    {
        if ($group === 'absent') {
            return DB::connection('exam')->table('registrations as r')
                ->leftJoin('preliminary_results as p', 'p.registration_id', '=', 'r.id')
                ->where('r.status', 'active')
                ->whereNull('p.id')
                ->count();
        }

        $query = $this->baseResultQuery();
        $this->applyGroup($query, $group);

        return $query->count();
    }

    /** @return array{GG:int,TT:int,GT:int,total:int} */
    private function categoryBreakdown(string $group): array
    {
        if ($group === 'active_registered') {
            $rows = DB::connection('exam')->table('registrations')
                ->where('status', 'active')
                ->selectRaw('cadre_category, COUNT(*) as aggregate')
                ->groupBy('cadre_category')
                ->pluck('aggregate', 'cadre_category');
        } elseif ($group === 'absent') {
            $rows = DB::connection('exam')->table('registrations as r')
                ->leftJoin('preliminary_results as p', 'p.registration_id', '=', 'r.id')
                ->where('r.status', 'active')
                ->whereNull('p.id')
                ->selectRaw('r.cadre_category, COUNT(*) as aggregate')
                ->groupBy('r.cadre_category')
                ->pluck('aggregate', 'r.cadre_category');
        } else {
            $query = $this->baseResultQuery();
            $this->applyGroup($query, $group);
            $rows = $query
                ->selectRaw('r.cadre_category, COUNT(*) as aggregate')
                ->groupBy('r.cadre_category')
                ->pluck('aggregate', 'r.cadre_category');
        }

        $gg = (int) ($rows[1] ?? 0);
        $tt = (int) ($rows[2] ?? 0);
        $gt = (int) ($rows[3] ?? 0);

        return ['GG' => $gg, 'TT' => $tt, 'GT' => $gt, 'total' => $gg + $tt + $gt];
    }

    private function applyGroup($query, string $group): void
    {
        match ($group) {
            'present_with_mark' => $query->where('p.candidate_status', 'active')->whereNotNull('p.mark'),
            'present_with_status_text' => $query->where('p.candidate_status', 'active')->whereNotNull('p.mark')
                ->whereNotNull('p.raw_candidate_status')->whereRaw("TRIM(p.raw_candidate_status) <> ''"),
            'cancelled_with_reason' => $query->where('p.candidate_status', 'cancelled')
                ->whereNotNull('p.raw_candidate_status')->whereRaw("TRIM(p.raw_candidate_status) <> ''"),
            'cancelled_without_reason' => $query->where('p.candidate_status', 'cancelled')
                ->where(fn ($q) => $q->whereNull('p.raw_candidate_status')->orWhereRaw("TRIM(p.raw_candidate_status) = ''")),
            default => null,
        };
    }
}
