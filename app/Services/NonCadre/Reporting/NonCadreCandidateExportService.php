<?php

namespace App\Services\NonCadre\Reporting;

use App\Services\Reporting\DbfReportWriter;
use App\Services\Reporting\SpreadsheetReportWriter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class NonCadreCandidateExportService
{
    public function __construct(
        private readonly NonCadreReportingService $reports,
        private readonly SpreadsheetReportWriter $xlsx,
        private readonly DbfReportWriter $dbf,
    ) {}

    /** @return array{0:string,1:string} */
    public function export(string $scope, string $format, string $outputPath, ?callable $progress = null): array
    {
        $scope = strtolower(trim($scope));
        $format = strtolower(trim($format));
        if (! in_array($scope, ['eligible', 'allocated'], true)) {
            throw new RuntimeException('Unsupported Non-Cadre candidate export scope.');
        }
        if (! in_array($format, ['xlsx', 'dbf'], true)) {
            throw new RuntimeException('Unsupported Non-Cadre candidate export format.');
        }

        $run = $this->reports->requireReady();
        $query = DB::connection('exam')->table('non_cadre_allocation_input_candidates as i')
            ->leftJoin('non_cadre_allocation_results as a', function ($join) use ($run): void {
                $join->on('a.registration_id', '=', 'i.registration_id')
                    ->where('a.allocation_run_id', '=', $run->id);
            })
            ->leftJoin('registrations as r', 'r.id', '=', 'i.registration_id')
            ->leftJoin('non_cadre_circular_posts as p', function ($join) use ($run): void {
                $join->on('p.id', '=', 'a.circular_post_id')
                    ->where('p.circular_version_id', '=', $run->circular_version_id);
            })
            ->where('i.allocation_run_id', $run->id)
            ->where('i.historical_excluded', false);

        if ($scope === 'allocated') {
            $query->whereNotNull('a.post_code');
        }

        $rows = $query->select([
            'r.user_id', 'i.reg', 'r.name', 'i.has_cff', 'i.has_em', 'i.has_phc',
            'i.allocation_ready_choices', 'i.common_merit_position', 'a.post_code', 'a.allocation_basis',
            'p.post_title', 'p.entity', 'p.ministry',
        ])->orderBy('i.common_merit_position')->orderBy('i.registration_id')->get();

        $normalized = $rows->map(fn (object $row): array => $this->normalize($row));
        $label = $scope === 'allocated' ? 'Allocated Candidates' : 'Allocation Eligible Candidates';

        if ($format === 'xlsx') {
            $headers = ['user','reg','name','cff','em','phc','allocation_ready_choice','common_merit_position','allocation_status','allocated_post_code','allocated_post_name','allocation_basis'];
            $values = $normalized->map(fn (array $row): array => array_values($row));
            $this->xlsx->write(
                $outputPath,
                $headers,
                $values,
                [1,2,3,4,5,6,7,9,10,11],
                $progress ? fn (int $current, int $total) => $progress($current, $total, 'Writing XLSX candidate records.') : null,
                $values->count(),
                $label,
            );
        } else {
            $dbfRows = $normalized->map(fn (array $row): array => [
                'USER' => $row['user'],
                'REG' => $row['reg'],
                'NAME' => $row['name'],
                'CFF' => $row['cff'],
                'EM' => $row['em'],
                'PHC' => $row['phc'],
                'ALOC_CH' => $row['allocation_ready_choice'],
                'COM_MERIT' => $row['common_merit_position'],
                'ALOC_STAT' => $row['allocation_status'],
                'POST_CODE' => $row['allocated_post_code'],
                'POST_NAME' => $row['allocated_post_name'],
                'ALOC_BASIS' => $row['allocation_basis'],
            ]);
            $this->dbf->write(
                $outputPath,
                $this->dbfFields(),
                $dbfRows,
                $progress ? fn (int $current, int $total) => $progress($current, $total, 'Writing DBF candidate records.') : null,
                $dbfRows->count(),
            );
        }

        return [$label, $scope];
    }

    private function normalize(object $row): array
    {
        $choices = json_decode((string) ($row->allocation_ready_choices ?? '[]'), true);
        if (! is_array($choices)) $choices = [];
        $postCode = trim((string) ($row->post_code ?? ''));
        $postNameParts = array_values(array_filter([
            trim((string) ($row->post_title ?? '')),
            trim((string) ($row->entity ?? '')),
            trim((string) ($row->ministry ?? '')),
        ], fn (string $value): bool => $value !== ''));

        return [
            'user' => (string) ($row->user_id ?? ''),
            'reg' => (string) ($row->reg ?? ''),
            'name' => (string) ($row->name ?? ''),
            'cff' => $this->yesNo((bool) ($row->has_cff ?? false)),
            'em' => $this->yesNo((bool) ($row->has_em ?? false)),
            'phc' => $this->yesNo((bool) ($row->has_phc ?? false)),
            'allocation_ready_choice' => implode('|', array_map('strval', array_values($choices))),
            'common_merit_position' => $row->common_merit_position === null ? null : (int) $row->common_merit_position,
            'allocation_status' => $postCode !== '' ? 'ALLOCATED' : 'UNALLOCATED',
            'allocated_post_code' => $postCode,
            'allocated_post_name' => $postCode !== '' ? implode(', ', $postNameParts) : '',
            'allocation_basis' => strtoupper(trim((string) ($row->allocation_basis ?? ''))),
        ];
    }

    private function yesNo(bool $value): string { return $value ? 'Y' : 'N'; }

    private function dbfFields(): array
    {
        return [
            ['name'=>'USER','type'=>'C','length'=>20],
            ['name'=>'REG','type'=>'C','length'=>20],
            ['name'=>'NAME','type'=>'C','length'=>100],
            ['name'=>'CFF','type'=>'C','length'=>1],
            ['name'=>'EM','type'=>'C','length'=>1],
            ['name'=>'PHC','type'=>'C','length'=>1],
            ['name'=>'ALOC_CH','type'=>'C','length'=>254],
            ['name'=>'COM_MERIT','type'=>'N','length'=>8],
            ['name'=>'ALOC_STAT','type'=>'C','length'=>12],
            ['name'=>'POST_CODE','type'=>'C','length'=>50],
            ['name'=>'POST_NAME','type'=>'C','length'=>254],
            ['name'=>'ALOC_BASIS','type'=>'C','length'=>10],
        ];
    }
}
