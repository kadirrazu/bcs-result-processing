<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationHistoricalMatch;
use App\Models\Registration;
use App\Services\Reporting\DbfReportWriter;
use App\Services\Reporting\SpreadsheetReportWriter;
use Illuminate\Support\Collection;
use RuntimeException;

final class ChoiceOptimizationHistoricalMatchExportService
{
    public function __construct(
        private readonly SpreadsheetReportWriter $xlsx,
        private readonly DbfReportWriter $dbf,
    ) {}

    public function export(string $format, string $path): int
    {
        $format = strtolower(trim($format));
        if (! in_array($format, ['xlsx', 'dbf'], true)) {
            throw new RuntimeException('Unsupported Historical Previous BCS match export format.');
        }

        $rows = $this->rows();

        if ($format === 'xlsx') {
            $headers = ['user_id', 'reg', 'name', 'matched_previous_bcs', 'previous_bcs_match_history'];
            $values = $rows->map(fn (array $row): array => [
                $row['user_id'], $row['reg'], $row['name'], $row['matched_previous_bcs'], $row['previous_bcs_match_history'],
            ]);
            $this->xlsx->write($path, $headers, $values, [1, 2, 3, 4, 5], null, $values->count(), 'Previous BCS Matches');
        } else {
            $fields = [
                ['name' => 'USER_ID', 'type' => 'C', 'length' => 10],
                ['name' => 'REG', 'type' => 'C', 'length' => 10],
                ['name' => 'NAME', 'type' => 'C', 'length' => 80],
                ['name' => 'MATCH_BCS', 'type' => 'C', 'length' => 80],
                ['name' => 'HISTORY', 'type' => 'C', 'length' => 254],
            ];
            $dbfRows = $rows->map(fn (array $row): array => [
                'USER_ID' => $row['user_id'],
                'REG' => $row['reg'],
                'NAME' => $row['name'],
                'MATCH_BCS' => $row['matched_previous_bcs'],
                'HISTORY' => $row['previous_bcs_match_history'],
            ]);
            $this->dbf->write($path, $fields, $dbfRows, null, $dbfRows->count());
        }

        return $rows->count();
    }

    /** @return Collection<int,array<string,string>> */
    public function rows(): Collection
    {
        // Historical Pull is built only from the current Written-qualified (Viva-eligible)
        // population. Restrict export to sources explicitly INCLUDED in this examination
        // and to confirmed positive matches; operator-confirmed REVIEW rows become matched.
        $matches = ChoiceOptimizationHistoricalMatch::query()
            ->with('source')
            ->whereHas('source', fn ($q) => $q->where('included_in_optimization', true))
            ->where('match_status', 'matched')
            ->orderBy('registration_id')
            ->orderBy('previous_bcs_number')
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            return collect();
        }

        $registrations = Registration::query()
            ->whereIn('id', $matches->pluck('registration_id')->map(fn ($id) => (int) $id)->unique()->values())
            ->get(['id', 'user_id', 'reg', 'name'])
            ->keyBy('id');

        return $matches
            ->groupBy('registration_id')
            ->map(function (Collection $candidateMatches, $registrationId) use ($registrations): array {
                $registration = $registrations->get((int) $registrationId);
                $bcsNumbers = $candidateMatches->pluck('previous_bcs_number')
                    ->map(fn ($value): int => (int) $value)->unique()->sort()->values();

                $history = $candidateMatches->map(function (ChoiceOptimizationHistoricalMatch $match): string {
                    $parts = [(int) $match->previous_bcs_number.'th BCS'];
                    if (filled($match->previous_reg)) {
                        $parts[] = 'Reg: '.trim((string) $match->previous_reg);
                    }
                    if (filled($match->previous_cadre)) {
                        $parts[] = 'Cadre: '.trim((string) $match->previous_cadre);
                    }
                    if ((string) $match->resolution_status === 'operator_confirmed') {
                        $parts[] = 'Operator Confirmed';
                    }
                    return implode(' | ', $parts);
                })->unique()->implode(' ; ');

                return [
                    'user_id' => (string) ($registration?->user_id ?? ''),
                    'reg' => (string) ($registration?->reg ?? $candidateMatches->first()?->current_reg ?? ''),
                    'name' => (string) ($registration?->name ?? ''),
                    'matched_previous_bcs' => $bcsNumbers->map(fn (int $bcs): string => $bcs.'th BCS')->implode(' | '),
                    'previous_bcs_match_history' => $history,
                ];
            })
            ->sortBy(fn (array $row) => str_pad($row['reg'], 20, '0', STR_PAD_LEFT))
            ->values();
    }
}
