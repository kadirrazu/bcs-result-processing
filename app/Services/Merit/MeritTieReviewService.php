<?php

namespace App\Services\Merit;

use App\Models\MeritProcessingRun;
use App\Models\MeritResult;
use App\Models\Registration;
use App\Models\TabulationResult;

final class MeritTieReviewService
{
    public function __construct(private readonly MeritRankingService $ranking) {}

    /** @return array{summary:array<string,array{groups:int,candidates:int}>,groups:array<int,array<string,mixed>>} */
    public function forRun(MeritProcessingRun $run): array
    {
        $tabRun = (int) data_get($run->source_snapshot, 'tabulation.processing_run_id', 0);
        $rows = TabulationResult::query()->where('processing_run_id', $tabRun)->orderBy('id')->get();
        $merits = MeritResult::query()->where('processing_run_id', $run->id)->get()->keyBy('tabulation_result_id');
        $registrations = Registration::query()->whereIn('id', $rows->pluck('registration_id'))->get(['id','name'])->keyBy('id');
        $groups = [];
        $summary = [];

        foreach (['common' => 'Common Merit', 'general' => 'General Merit', 'technical' => 'Technical Merit'] as $scope => $label) {
            $scopeGroups = $this->ranking->businessTieGroups($rows, $scope);
            $candidateCount = 0;
            foreach ($scopeGroups as $index => $members) {
                $candidateCount += count($members);
                $candidateRows = [];
                foreach ($members as $row) {
                    [$grand, $written] = $this->ranking->applicableScores($row, $scope);
                    $merit = $merits->get($row->id);
                    $candidateRows[] = [
                        'registration_id' => (int) $row->registration_id,
                        'reg' => (string) $row->reg,
                        'name' => (string) ($registrations->get($row->registration_id)?->name ?? ''),
                        'grand_total' => $grand,
                        'written_total' => $written,
                        'preliminary_mark' => $row->preliminary_mark,
                        'dob' => $row->birth_date,
                        'graduation_year' => $row->graduation_year,
                        'final_merit_position' => $merit?->{$scope.'_merit_position'},
                    ];
                }
                usort($candidateRows, fn (array $a, array $b): int => ($a['final_merit_position'] ?? PHP_INT_MAX) <=> ($b['final_merit_position'] ?? PHP_INT_MAX));
                $groups[] = [
                    'scope' => $scope,
                    'scope_label' => $label,
                    'group_no' => $index + 1,
                    'candidates' => $candidateRows,
                ];
            }
            $summary[$scope] = ['groups' => count($scopeGroups), 'candidates' => $candidateCount];
        }

        return ['summary' => $summary, 'groups' => $groups];
    }
}
