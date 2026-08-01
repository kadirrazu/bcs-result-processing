<?php

namespace App\Services\Written;

use App\Enums\WrittenProcessingStatus;
use App\Models\WrittenImportBatch;
use App\Models\WrittenProcessingState;
use App\Models\WrittenReconciliationReport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Builds the Written eligible/appeared/absence reconciliation from finalized Preliminary PASS facts. */
final class WrittenReconciliationService
{
    public function generate(int $actorId): WrittenReconciliationReport
    {
        $state = WrittenProcessingState::query()->firstOrCreate(['id' => 1], ['status' => WrittenProcessingStatus::NotStarted->value]);
        $batchId = (int) ($state->latest_import_batch_id ?? 0);
        $batch = $batchId > 0 ? WrittenImportBatch::query()->find($batchId) : null;

        if ($batch === null || $batch->status !== 'approved') {
            throw new RuntimeException('Approve the current Written import before generating reconciliation.');
        }

        $eligibleByCategory = $this->eligibleByCategory();
        $writtenRowsByCategory = $this->writtenByCategory();
        $missingByCategory = $this->missingWrittenByCategory();
        $allAbsentByCategory = [1 => 0, 2 => 0, 3 => 0];
        $partialMandatoryAbsentByCategory = [1 => 0, 2 => 0, 3 => 0];

        DB::connection('exam')->table('written_results')->select(['id', 'cadre_category'])->orderBy('id')
            ->chunkById(1500, function ($rows) use (&$allAbsentByCategory, &$partialMandatoryAbsentByCategory): void {
                $resultIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
                $marks = DB::connection('exam')->table('written_candidate_marks')
                    ->whereIn('written_result_id', $resultIds)
                    ->where('is_applicable', 1)
                    ->get(['written_result_id', 'attendance_status'])
                    ->groupBy('written_result_id');

                foreach ($rows as $row) {
                    $category = (int) $row->cadre_category;
                    $candidateMarks = $marks->get((int) $row->id, collect());
                    if ($candidateMarks->isEmpty()) {
                        continue;
                    }

                    $absent = $candidateMarks->where('attendance_status', 'absent')->count();
                    if ($absent === $candidateMarks->count()) {
                        $allAbsentByCategory[$category]++;
                    } elseif ($absent > 0) {
                        $partialMandatoryAbsentByCategory[$category]++;
                    }
                }
            }, 'id');

        // A candidate with a Written row but ABS/AAA in every applicable subject did not actually appear.
        $appearedByCategory = $this->subtractCategory($writtenRowsByCategory, $allAbsentByCategory);
        $completelyAbsent = $this->sumCategory($missingByCategory, $allAbsentByCategory);

        $summary = [
            'eligible' => $this->metric($eligibleByCategory),
            'appeared' => $this->metric($appearedByCategory),
            'completely_absent' => $this->metric($completelyAbsent),
            // Kept as audit/detail components, but not shown as duplicate top-level metrics in the UI.
            'missing_from_file' => $this->metric($missingByCategory),
            'all_applicable_subjects_absent' => $this->metric($allAbsentByCategory),
            'mandatory_subject_absent' => $this->metric($partialMandatoryAbsentByCategory),
            'generated_from_batch_id' => $batchId,
        ];

        $report = WrittenReconciliationReport::query()->create([
            'source_batch_id' => $batchId,
            'summary' => $summary,
            'generated_by' => $actorId,
            'generated_at' => now(),
        ]);

        $state->update([
            'status' => WrittenProcessingStatus::ReconciliationGenerated->value,
            'reconciliation_generated_by' => $actorId,
            'reconciliation_generated_at' => now(),
            'latest_reconciliation_report_id' => $report->id,
            'paper_crash_processed_at' => null,
            'paper_crash_processed_by' => null,
            'latest_processing_run_id' => null,
            'result_finalized_at' => null,
            'result_finalized_by' => null,
            'summary' => $summary,
            'is_stale' => false,
            'stale_reason' => null,
        ]);

        return $report;
    }

    /** @return array<int,int> */
    private function eligibleByCategory(): array
    {
        return $this->categoryCounts(
            DB::connection('exam')->table('preliminary_results as p')
                ->join('registrations as r', 'r.id', '=', 'p.registration_id')
                ->where('p.result_status', 'pass')
        );
    }

    /** @return array<int,int> */
    private function writtenByCategory(): array
    {
        return $this->categoryCounts(DB::connection('exam')->table('written_results as r'), 'r.cadre_category');
    }

    /** @return array<int,int> */
    private function missingWrittenByCategory(): array
    {
        $query = DB::connection('exam')->table('preliminary_results as p')
            ->join('registrations as r', 'r.id', '=', 'p.registration_id')
            ->leftJoin('written_results as w', 'w.registration_id', '=', 'p.registration_id')
            ->where('p.result_status', 'pass')
            ->whereNull('w.id');

        return $this->categoryCounts($query);
    }

    /** @return array<int,int> */
    private function categoryCounts($query, string $column = 'r.cadre_category'): array
    {
        $out = [1 => 0, 2 => 0, 3 => 0];
        $rows = $query->selectRaw("{$column} as category, COUNT(*) as aggregate")->groupBy($column)->get();
        foreach ($rows as $row) {
            if (isset($out[(int) $row->category])) {
                $out[(int) $row->category] = (int) $row->aggregate;
            }
        }
        return $out;
    }

    /** @param array<int,int> $counts */
    private function metric(array $counts): array
    {
        return [
            'total' => array_sum($counts),
            'GG' => (int) ($counts[1] ?? 0),
            'TT' => (int) ($counts[2] ?? 0),
            'GT' => (int) ($counts[3] ?? 0),
        ];
    }

    /** @param array<int,int> $a @param array<int,int> $b @return array<int,int> */
    private function sumCategory(array $a, array $b): array
    {
        return [
            1 => ($a[1] ?? 0) + ($b[1] ?? 0),
            2 => ($a[2] ?? 0) + ($b[2] ?? 0),
            3 => ($a[3] ?? 0) + ($b[3] ?? 0),
        ];
    }

    /** @param array<int,int> $a @param array<int,int> $b @return array<int,int> */
    private function subtractCategory(array $a, array $b): array
    {
        return [
            1 => max(0, ($a[1] ?? 0) - ($b[1] ?? 0)),
            2 => max(0, ($a[2] ?? 0) - ($b[2] ?? 0)),
            3 => max(0, ($a[3] ?? 0) - ($b[3] ?? 0)),
        ];
    }
}
