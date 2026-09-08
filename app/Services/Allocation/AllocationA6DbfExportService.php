<?php

namespace App\Services\Allocation;

use App\Models\AllocationA4Result;
use App\Models\AllocationA5Run;
use App\Models\ChoiceOptimizationHistoricalChoice;
use App\Models\MeritResult;
use App\Models\Registration;
use App\Models\TabulationResult;
use App\Services\Reporting\DbfReportWriter;
use App\Support\Examinations\ExaminationContext;
use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

final class AllocationA6DbfExportService
{
    public function __construct(
        private readonly AllocationA6ReportService $reports,
        private readonly AllocationResultDispositionService $dispositions,
        private readonly DbfReportWriter $writer,
        private readonly ExaminationContext $context,
    ) {}

    /** @return array{0:string,1:string} */
    public function export(
        AllocationA5Run $a5,
        string $scope,
        string $outputPath,
        ?string $examName = null,
        ?callable $progress = null,
    ): array {
        $scope = strtolower(trim($scope));
        if (! in_array($scope, ['tabulated', 'allocated'], true)) {
            throw new RuntimeException('Unsupported DBF export scope.');
        }

        $sourceRows = $scope === 'tabulated'
            ? $this->reports->tabulationEligibleQuery()->orderBy('reg')->get(['registration_id', 'reg'])
            : $this->allocatedRows($a5);

        $registrationIds = $sourceRows->pluck('registration_id')->map(fn ($id) => (int) $id)->unique()->values();
        $data = $this->preload($a5, $registrationIds);
        $total = $sourceRows->count();

        $rows = function () use ($sourceRows, $data): \Generator {
            foreach ($sourceRows as $source) {
                $registrationId = (int) $source->registration_id;
                yield $this->row($registrationId, (string) $source->reg, $data);
            }
        };

        $this->writer->write(
            $outputPath,
            $this->fields($scope),
            $rows(),
            $progress ? fn (int $current, int $count) => $progress($current, $count, 'Writing legacy DBF records.') : null,
            $total,
        );

        $base = $this->examSlug($examName).'-'.($scope === 'tabulated' ? 'tabulated' : 'allocated');
        return [$outputPath, $base.'-'.now()->format('Ymd-His').'.dbf'];
    }

    /** @return Collection<int,AllocationA4Result> */
    private function allocatedRows(AllocationA5Run $a5): Collection
    {
        $query = AllocationA4Result::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id);

        return $this->dispositions
            ->applyPublishedOnly($query, $a5, 'allocation_a4_results.registration_id')
            ->orderBy('cadre_code')
            ->orderBy('merit_position')
            ->orderBy('reg')
            ->get(['registration_id', 'reg']);
    }

    /** @return array<string,Collection> */
    private function preload(AllocationA5Run $a5, Collection $registrationIds): array
    {
        $tabRunId = $this->reports->currentTabulationRunId();
        $meritRunId = $this->reports->currentMeritRunId();
        if ($tabRunId === null || $meritRunId === null) {
            throw new RuntimeException('Current finalized Tabulation/Merit source is unavailable for DBF export.');
        }

        $registrations = Registration::query()->whereIn('id', $registrationIds)->get()->keyBy('id');
        $tabs = TabulationResult::query()
            ->where('processing_run_id', $tabRunId)
            ->whereIn('registration_id', $registrationIds)
            ->get()->keyBy('registration_id');
        $merits = MeritResult::query()
            ->where('processing_run_id', $meritRunId)
            ->whereIn('registration_id', $registrationIds)
            ->get()->keyBy('registration_id');
        $optimized = ChoiceOptimizationHistoricalChoice::query()
            ->whereIn('registration_id', $registrationIds)
            ->get()->keyBy('registration_id');
        $allocationQuery = AllocationA4Result::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
            ->whereIn('registration_id', $registrationIds);
        $allocations = $this->dispositions
            ->applyPublishedOnly($allocationQuery, $a5, 'allocation_a4_results.registration_id')
            ->get()->keyBy('registration_id');
        $dispositionMap = $this->dispositions->dispositionMap($a5, $registrationIds);

        if ($optimized->count() !== $registrationIds->count()) {
            throw new RuntimeException('Finalized Choice Optimization allocation-ready output is incomplete for DBF export.');
        }

        $choiceCodes = $optimized->flatMap(fn ($row) => (array) ($row->final_choice_codes ?? []))
            ->map(fn ($code) => (int) $code)->filter()->unique()->values();
        $allocationCodes = $allocations->pluck('cadre_code')->map(fn ($code) => (int) $code)->filter()->unique()->values();

        return [
            'registrations' => $registrations,
            'tabulation' => $tabs,
            'merit' => $merits,
            'optimized' => $optimized,
            'allocation' => $allocations,
            'dispositions' => $dispositionMap,
            'abbreviations' => $this->reports->abbreviations($choiceCodes->merge($allocationCodes)->unique()->values()),
        ];
    }

    /** @param array<string,Collection> $data @return array<string,mixed> */
    private function row(int $registrationId, string $fallbackReg, array $data): array
    {
        $registration = $data['registrations']->get($registrationId);
        $tab = $data['tabulation']->get($registrationId);
        $merit = $data['merit']->get($registrationId);
        $optimized = $data['optimized']->get($registrationId);
        $allocation = $data['allocation']->get($registrationId);
        $disposition = $data['dispositions']->get($registrationId);
        $dispositionStatus = strtoupper(trim((string) ($disposition?->status ?? '')));
        $withheld = $dispositionStatus === AllocationResultDispositionService::WITHHELD;
        $cancelled = $dispositionStatus === AllocationResultDispositionService::CANCELLED;
        $choices = array_values(array_filter(
            (array) ($optimized?->final_choice_codes ?? []),
            fn ($value) => filled($value)
        ));

        return [
            'REG' => (string) ($registration?->reg ?? $tab?->reg ?? $fallbackReg),
            'USER_ID' => (string) ($registration?->user_id ?? $tab?->user_id ?? ''),
            'CATEGORY' => $registration?->cadre_category?->code() ?? $this->categoryCode($tab?->cadre_category),
            'TRACK' => $this->enumValue($tab?->written_qualified_track ?? $merit?->written_qualified_track),
            'CFF' => $this->yesNo((bool) ($registration?->has_ff_quota ?? false)),
            'EM' => $this->yesNo((bool) ($registration?->has_em_quota ?? false)),
            'PHC' => $this->yesNo((bool) ($registration?->has_phc_quota ?? false)),
            'GEN_TOTAL' => $tab?->general_grand_total,
            'TECH_TOTAL' => $tab?->technical_grand_total,
            'COM_MERIT' => $merit?->common_merit_position,
            'GEN_MERIT' => $merit?->general_merit_position,
            'TECH_MERIT' => $merit?->technical_merit_position,
            'ALLM_TECH' => MeritResult::allMeritTechJson($merit?->all_merit_tech),
            'ALOC_CH_CD' => implode(' ', array_map('strval', $choices)),
            'ALOC_CH_AB' => collect($choices)
                ->map(fn ($code) => (string) ($data['abbreviations']->get((int) $code) ?? 'UNMAPPED'))
                ->implode(' '),
            'CADRE_CODE' => $allocation?->cadre_code,
            'CADRE_ABBR' => (string) ($data['abbreviations']->get((int) ($allocation?->cadre_code ?? 0)) ?? ''),
            'ALOC_BASIS' => strtoupper(trim((string) ($allocation?->allocation_basis ?? ''))),
            'WITHHELD' => $withheld ? 'TRUE' : '',
            'CANCELLED' => $cancelled ? 'TRUE' : '',
        ];
    }

    /** @return array<int,array{name:string,type:string,length:int,decimals?:int}> */
    public function fields(string $scope = 'allocated'): array
    {
        $fields = [
            ['name' => 'REG', 'type' => 'C', 'length' => 8],
            ['name' => 'USER_ID', 'type' => 'C', 'length' => 20],
            ['name' => 'CATEGORY', 'type' => 'C', 'length' => 2],
            ['name' => 'TRACK', 'type' => 'C', 'length' => 2],
            ['name' => 'CFF', 'type' => 'C', 'length' => 1],
            ['name' => 'EM', 'type' => 'C', 'length' => 1],
            ['name' => 'PHC', 'type' => 'C', 'length' => 1],
            ['name' => 'GEN_TOTAL', 'type' => 'N', 'length' => 10, 'decimals' => 2],
            ['name' => 'TECH_TOTAL', 'type' => 'N', 'length' => 10, 'decimals' => 2],
            ['name' => 'COM_MERIT', 'type' => 'N', 'length' => 8],
            ['name' => 'GEN_MERIT', 'type' => 'N', 'length' => 8],
            ['name' => 'TECH_MERIT', 'type' => 'N', 'length' => 8],
            ['name' => 'ALLM_TECH', 'type' => 'C', 'length' => 254],
            ['name' => 'ALOC_CH_CD', 'type' => 'C', 'length' => 254],
            ['name' => 'ALOC_CH_AB', 'type' => 'C', 'length' => 254],
            ['name' => 'CADRE_CODE', 'type' => 'N', 'length' => 8],
            ['name' => 'CADRE_ABBR', 'type' => 'C', 'length' => 10],
            ['name' => 'ALOC_BASIS', 'type' => 'C', 'length' => 10],
        ];

        if (strtolower(trim($scope)) === 'tabulated') {
            $fields[] = ['name' => 'WITHHELD', 'type' => 'C', 'length' => 4];
            $fields[] = ['name' => 'CANCELLED', 'type' => 'C', 'length' => 4];
        }

        return $fields;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'Y' : 'N';
    }

    private function enumValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return strtoupper((string) $value->value);
        }
        return strtoupper(trim((string) ($value ?? '')));
    }

    private function categoryCode(mixed $value): string
    {
        return match ((int) $value) {
            1 => 'GG',
            2 => 'TT',
            3 => 'GT',
            default => '',
        };
    }

    private function examSlug(?string $examName): string
    {
        return Str::slug($examName ?: (string) ($this->context->current()?->name ?? 'allocation-report')) ?: 'allocation-report';
    }
}
