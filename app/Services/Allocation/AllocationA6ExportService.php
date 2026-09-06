<?php

namespace App\Services\Allocation;

use App\Models\AllocationA4Result;
use App\Models\AllocationA5Run;
use App\Models\AllocationA6ExportAudit;
use App\Models\ChoiceOptimizationEffectiveChoice;
use App\Models\ChoiceOptimizationHistoricalChoice;
use App\Models\ChoiceSourceItem;
use App\Models\ChoiceValidationResult;
use App\Models\District;
use App\Models\Gender;
use App\Models\MeritResult;
use App\Models\PreliminaryResult;
use App\Models\Registration;
use App\Models\TabulationResult;
use App\Models\VivaResult;
use App\Models\WrittenResult;
use App\Services\Documents\DocxPlaceholderTemplateService;
use App\Services\Reporting\ReportExportFileStore;
use App\Services\Reporting\SpreadsheetReportWriter;
use App\Support\Examinations\ExaminationContext;
use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use UnitEnum;
use ZipArchive;

/**
 * Allocation-specific A6 publishing engine.
 *
 * Business/source selection stays here. Generic file persistence and XLSX
 * mechanics live under Services/Reporting so a future dedicated Reporting
 * Module can reuse them without inheriting Allocation business rules.
 */
final class AllocationA6ExportService
{
    public function __construct(
        private readonly AllocationA6ReportService $reports,
        private readonly AllocationA6ExcelFieldCatalog $fieldCatalog,
        private readonly AllocationA6SummaryService $summary,
        private readonly AllocationResultDispositionService $dispositions,
        private readonly ReportExportFileStore $files,
        private readonly SpreadsheetReportWriter $spreadsheets,
        private readonly ExaminationContext $context,
    ) {}

    public function consolidatedTxt(
        AllocationA5Run $a5,
        int $perLine,
        string $reportTitle,
        ?string $outputPath = null,
        ?string $examName = null,
        ?callable $progress = null,
    ): array {
        $perLine = max(1, min(20, $perLine));
        $path = $outputPath ?: $this->legacyTempPath('txt');
        File::put($path, $this->txtContent($a5, $perLine, $reportTitle, null, $examName, $progress));

        return [$path, $this->timestampedDownloadName($this->examSlug($examName).'-final-allocation', 'txt')];
    }

    public function cadreTxtZip(
        AllocationA5Run $a5,
        int $perLine,
        string $reportTitle,
        ?string $outputPath = null,
        ?string $examName = null,
        ?callable $progress = null,
    ): array {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZIP extension is required.');
        }

        $perLine = max(1, min(20, $perLine));
        $zipPath = $outputPath ?: $this->legacyTempPath('zip');
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create ZIP.');
        }

        $cadres = $this->reports->cadres($a5);
        $total = max(1, $cadres->count());
        foreach ($cadres as $index => $row) {
            $code = (int) $row['code'];
            $abbr = (string) $row['abbr'];
            $zip->addFromString(
                $this->timestampedDownloadName($code.'-'.$abbr, 'txt'),
                $this->txtContent($a5, $perLine, $reportTitle, $code, $examName)
            );
            if ($progress) $progress($index + 1, $total, 'Preparing cadre-wise TXT files.');
        }
        $zip->close();

        return [$zipPath, $this->timestampedDownloadName($this->examSlug($examName).'-cadre-wise-final-allocation-txt', 'zip')];
    }

    private function txtContent(
        AllocationA5Run $a5,
        int $perLine,
        string $reportTitle,
        ?int $onlyCadre,
        ?string $examName = null,
        ?callable $progress = null,
    ): string {
        $exam = $examName ?: (string) ($this->context->current()?->name ?? 'Selected Examination');
        $publishedTotalQuery = AllocationA4Result::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id);
        $totalAllocated = $this->dispositions
            ->applyPublishedOnly($publishedTotalQuery, $a5, 'allocation_a4_results.registration_id')
            ->count();

        $lines = [
            'Exam Title: '.$exam,
            'Report Title: '.$reportTitle,
            'Generation Time: '.now()->format('d-m-Y h:i:s A'),
            'TOTAL ALLOCATED = '.$totalAllocated,
            '',
        ];

        $cadres = $this->reports->cadres($a5)
            ->when($onlyCadre !== null, fn (Collection $rows) => $rows->where('code', $onlyCadre)->values());
        $totalCadres = max(1, $cadres->count());

        foreach ($cadres as $index => $row) {
            $code = (int) $row['code'];
            $regQuery = AllocationA4Result::query()
                ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
                ->where('cadre_code', $code);
            $regs = $this->dispositions->applyPublishedOnly($regQuery, $a5, 'allocation_a4_results.registration_id')
                ->orderBy('merit_position')->orderBy('reg')->pluck('reg');

            $entry = $row['entry'] ?? null;
            $cadreName = trim((string) ($entry?->cadre_name_snapshot ?? ''));
            $postName = trim((string) ($entry?->post_name_snapshot ?? ''));
            $titleParts = ['#'.$code, (string) $row['abbr']];
            if ($cadreName !== '') $titleParts[] = $cadreName;
            if ($postName !== '' && strcasecmp($postName, $cadreName) !== 0) $titleParts[] = $postName;
            $lines[] = implode(' - ', $titleParts);
            $lines[] = str_repeat('-', 72);
            if ($regs->isEmpty()) {
                $lines[] = 'NO ELIGIBLE CANDIDATE';
            } else {
                foreach ($regs->chunk($perLine) as $chunk) {
                    $lines[] = $chunk->implode(' ');
                }
            }
            $lines[] = 'TOTAL = '.$regs->count();
            $lines[] = '';
            $lines[] = '';

            if ($progress) $progress($index + 1, $totalCadres, 'Formatting TXT cadre sections.');
        }

        return implode("\r\n", $lines)."\r\n";
    }

    /** @return array{0:string,1:string} */
    public function xlsx(
        AllocationA5Run $a5,
        string $scope,
        ?int $cadreCode = null,
        ?string $outputPath = null,
        ?string $examName = null,
        ?callable $progress = null,
    ): array {
        return $this->xlsxSelected(
            $a5,
            $scope,
            $cadreCode,
            $this->fieldCatalog->defaultKeys(),
            $outputPath,
            $examName,
            $progress,
        );
    }

    /** @param array<int,string> $selectedFields @return array{0:string,1:string} */
    public function dynamicXlsx(
        AllocationA5Run $a5,
        string $scope,
        ?int $cadreCode,
        array $selectedFields,
        ?string $outputPath = null,
        ?string $examName = null,
        ?callable $progress = null,
    ): array {
        return $this->xlsxSelected(
            $a5,
            $scope,
            $cadreCode,
            $this->fieldCatalog->validateSelection($selectedFields),
            $outputPath,
            $examName,
            $progress,
        );
    }

    /** @return array{0:string,1:string} */
    public function allocationSummaryXlsx(
        AllocationA5Run $a5,
        ?string $outputPath = null,
        ?string $examName = null,
        ?callable $progress = null,
    ): array {
        $rows = $this->summary->rows($a5);
        $path = $outputPath ?: $this->legacyTempPath('xlsx');
        $total = $rows->count();

        $rowGenerator = function () use ($rows): \Generator {
            foreach ($rows as $row) {
                yield $this->summary->excelRow($row);
            }
        };

        $this->spreadsheets->write(
            $path,
            $this->summary->excelHeaders(),
            $rowGenerator(),
            [2, 3, 4],
            $progress ? fn (int $current, int $rowTotal) => $progress($current, $rowTotal, 'Writing Allocation Summary workbook.') : null,
            $total,
            'Allocation Summary',
        );

        return [
            $path,
            $this->timestampedDownloadName($this->examSlug($examName).'-allocation-summary', 'xlsx'),
        ];
    }

    /** @return array{0:string,1:string} */
    public function allocationShortSummaryXlsx(
        AllocationA5Run $a5,
        ?string $outputPath = null,
        ?string $examName = null,
        ?callable $progress = null,
    ): array {
        $rows = $this->summary->rows($a5);
        $path = $outputPath ?: $this->legacyTempPath('xlsx');
        $total = $rows->count();

        $rowGenerator = function () use ($rows): \Generator {
            foreach ($rows as $row) {
                yield $this->summary->shortExcelRow($row);
            }
        };

        $this->spreadsheets->write(
            $path,
            $this->summary->shortExcelHeaders(),
            $rowGenerator(),
            [2, 3, 4],
            $progress ? fn (int $current, int $rowTotal) => $progress($current, $rowTotal, 'Writing Short Allocation Summary workbook.') : null,
            $total,
            'Short Allocation Summary',
        );

        return [
            $path,
            $this->timestampedDownloadName($this->examSlug($examName).'-allocation-short-summary', 'xlsx'),
        ];
    }

    /** @param array<int,string> $selectedFields */
    private function xlsxSelected(
        AllocationA5Run $a5,
        string $scope,
        ?int $cadreCode,
        array $selectedFields,
        ?string $outputPath,
        ?string $examName,
        ?callable $progress,
    ): array {
        $scope = in_array($scope, ['tabulation_eligible','allocated','cadre'], true) ? $scope : 'tabulation_eligible';
        if ($scope === 'cadre' && ! $cadreCode) {
            throw new RuntimeException('Cadre code is required.');
        }

        $rows = $scope === 'tabulation_eligible'
            ? $this->reports->tabulationEligibleQuery()->orderBy('reg')->get()
            : AllocationA4Result::query()->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
                ->when($scope === 'cadre', fn ($q) => $q->where('cadre_code', $cadreCode))
                ->orderBy($scope === 'cadre' ? 'merit_position' : 'cadre_code')->orderBy('merit_position')->orderBy('reg')->get();

        $registrationIds = $rows->pluck('registration_id')->map(fn ($v) => (int) $v)->unique()->values();
        $data = $this->preloadExcelSources($a5, $registrationIds);
        $allLabels = $this->fieldCatalog->fields();
        $headers = ['SL'];
        foreach ($selectedFields as $field) {
            $headers[] = $allLabels[$field];
        }

        $textColumns = [];
        foreach ($selectedFields as $index => $field) {
            if (in_array($field, ['registration.reg', 'registration.user_id'], true)) {
                $textColumns[] = $index + 2; // SL is column 1.
            }
        }

        $total = $rows->count();
        $rowGenerator = function () use ($rows, $data, $selectedFields): \Generator {
            $sl = 1;
            foreach ($rows as $source) {
                $registrationId = (int) $source->registration_id;
                $values = [$sl++];
                foreach ($selectedFields as $field) {
                    $values[] = $this->excelFieldValue($field, $source, $registrationId, $data);
                }
                yield $values;
            }
        };

        $path = $outputPath ?: $this->legacyTempPath('xlsx');
        $this->spreadsheets->write(
            $path,
            $headers,
            $rowGenerator(),
            $textColumns,
            $progress ? fn (int $current, int $count) => $progress($current, $count, 'Writing Excel rows.') : null,
            $total,
            'Final Report',
        );

        $suffix = $scope === 'cadre' ? 'cadre-'.$cadreCode : str_replace('_', '-', $scope);
        return [$path, $this->timestampedDownloadName($this->examSlug($examName).'-'.$suffix, 'xlsx')];
    }

    /** @return array<string,Collection> */
    private function preloadExcelSources(AllocationA5Run $a5, Collection $registrationIds): array
    {
        $tabRunId = $this->reports->currentTabulationRunId();
        $meritRunId = $this->reports->currentMeritRunId();

        $choices = ChoiceValidationResult::query()->whereIn('registration_id', $registrationIds)->orderBy('validation_version')->get()->keyBy('registration_id');
        $sourceItems = ChoiceSourceItem::query()
            ->whereIn('choice_validation_source_id', $choices->pluck('choice_source_id')->filter()->unique()->values())
            ->orderBy('position')->get()->groupBy('choice_validation_source_id');
        $registrationChoices = $choices->mapWithKeys(function ($choice) use ($sourceItems): array {
            $codes = $sourceItems->get((int) $choice->choice_source_id, collect())
                ->pluck('choice_code')->filter(fn ($value) => filled($value))->values()->all();
            return [(int) $choice->registration_id => $codes];
        });

        $registrations = Registration::query()->whereIn('id', $registrationIds)->get()->keyBy('id');
        $sexCodes = $registrations->pluck('sex_code')->filter(fn ($value) => filled($value))->unique()->values();
        $districtCodes = $registrations->pluck('district_code')->filter(fn ($value) => filled($value))->unique()->values();
        $choiceCodes = $registrationChoices->flatten()
            ->merge($choices->pluck('validated_choice_codes')->flatten())
            ->merge(ChoiceOptimizationEffectiveChoice::query()->whereIn('registration_id', $registrationIds)->get()->flatMap(fn ($row) => array_merge((array) ($row->omr_override_choice_codes ?? []), (array) ($row->effective_choice_codes ?? []))))
            ->merge(ChoiceOptimizationHistoricalChoice::query()->whereIn('registration_id', $registrationIds)->get()->flatMap(fn ($row) => (array) ($row->final_choice_codes ?? [])))
            ->map(fn ($value) => (int) $value)->filter()->unique()->values();
        $allocationCodes = AllocationA4Result::query()->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
            ->whereIn('registration_id', $registrationIds)->pluck('cadre_code')->map(fn ($value) => (int) $value)->filter();
        $abbreviations = $this->reports->abbreviations($choiceCodes->merge($allocationCodes)->unique()->values());

        return [
            'registrations' => $registrations,
            'genders' => Gender::query()->whereIn('code', $sexCodes)->pluck('name', 'code'),
            'districts' => District::query()->whereIn('code', $districtCodes)->pluck('name', 'code'),
            'abbreviations' => $abbreviations,
            'preliminary' => PreliminaryResult::query()->whereIn('registration_id', $registrationIds)->get()->keyBy('registration_id'),
            'written' => WrittenResult::query()->whereIn('registration_id', $registrationIds)->get()->keyBy('registration_id'),
            'viva' => VivaResult::query()->whereIn('registration_id', $registrationIds)->get()->keyBy('registration_id'),
            'tabulation' => $tabRunId ? TabulationResult::query()->where('processing_run_id', $tabRunId)->whereIn('registration_id', $registrationIds)->get()->keyBy('registration_id') : collect(),
            'merit' => $meritRunId ? MeritResult::query()->where('processing_run_id', $meritRunId)->whereIn('registration_id', $registrationIds)->get()->keyBy('registration_id') : collect(),
            'choice' => $choices,
            'registration_choice' => $registrationChoices,
            'effective_choice' => ChoiceOptimizationEffectiveChoice::query()->whereIn('registration_id', $registrationIds)->get()->keyBy('registration_id'),
            'optimized' => ChoiceOptimizationHistoricalChoice::query()->whereIn('registration_id', $registrationIds)->orderBy('id')->get()->keyBy('registration_id'),
            'allocation' => AllocationA4Result::query()->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)->whereIn('registration_id', $registrationIds)->get()->keyBy('registration_id'),
            'disposition' => $this->dispositions->dispositionMap($a5, $registrationIds),
            'a5' => $a5->candidateResults()->whereIn('registration_id', $registrationIds)->get()->keyBy('registration_id'),
        ];
    }

    /** @param array<string,Collection> $data */
    private function excelFieldValue(string $field, mixed $source, int $registrationId, array $data): mixed
    {
        $reg = $data['registrations']->get($registrationId);
        $pre = $data['preliminary']->get($registrationId);
        $written = $data['written']->get($registrationId);
        $viva = $data['viva']->get($registrationId);
        $tab = $data['tabulation']->get($registrationId);
        $merit = $data['merit']->get($registrationId);
        $choice = $data['choice']->get($registrationId);
        $effectiveChoice = $data['effective_choice']->get($registrationId);
        $optimized = $data['optimized']->get($registrationId);
        $allocation = $data['allocation']->get($registrationId);
        $disposition = $data['disposition']->get($registrationId);
        $allocationStatus = $allocation ? strtoupper((string) ($disposition?->status ?: 'ACTIVE')) : '';
        $a5 = $data['a5']->get($registrationId);

        return match ($field) {
            'registration.reg' => (string) ($reg?->reg ?? $source->reg ?? ''),
            'registration.user_id' => (string) ($reg?->user_id ?? ''),
            'registration.name' => (string) ($reg?->name ?? ''),
            'registration.father_name' => (string) ($reg?->father_name ?? ''),
            'registration.mother_name' => (string) ($reg?->mother_name ?? ''),
            'registration.dob' => $reg?->birth_date?->format('Y-m-d'),
            'registration.sex_code' => (string) ($reg?->sex_code ?? ''),
            'registration.sex' => $this->uppercaseText($data['genders']->get($reg?->sex_code)),
            'registration.district_code' => (string) ($reg?->district_code ?? ''),
            'registration.district_name' => (string) ($data['districts']->get($reg?->district_code) ?? ''),
            'registration.cadre_category' => $reg?->cadre_category?->code() ?? '',
            'registration.bachelor_subject' => (string) ($reg?->bachelor_subject_code ?? ''),
            'registration.prs' => (string) ($reg?->post_related_subject_code ?? ''),
            'registration.cff' => $reg?->has_ff_quota ? 'YES' : 'NO',
            'registration.em' => $reg?->has_em_quota ? 'YES' : 'NO',
            'registration.phc' => $reg?->has_phc_quota ? 'YES' : 'NO',
            'preliminary.mark' => $pre?->mark,
            'preliminary.result' => $this->uppercaseText($this->enumValue($pre?->result_status)),
            'written.track' => $this->enumValue($written?->written_qualified_track),
            'written.general_total' => $written?->general_counted_total,
            'written.technical_total' => $written?->technical_counted_total,
            'written.general_result' => $this->uppercaseText($this->enumValue($written?->general_result_status)),
            'written.technical_result' => $this->uppercaseText($this->enumValue($written?->technical_result_status)),
            'viva.mark' => $viva?->mark,
            'viva.result' => $this->uppercaseText($this->enumValue($viva?->viva_result_status)),
            'tabulation.general_grand_total' => $tab?->general_grand_total,
            'tabulation.technical_grand_total' => $tab?->technical_grand_total,
            'tabulation.general_merit_eligible' => $tab?->general_merit_eligible ? 'YES' : 'NO',
            'tabulation.technical_merit_eligible' => $tab?->technical_merit_eligible ? 'YES' : 'NO',
            'choice.registration' => $this->choiceCodesText((array) ($data['registration_choice']->get($registrationId, []))),
            'choice.registration_abbr' => $this->choiceAbbreviationsText((array) ($data['registration_choice']->get($registrationId, [])), $data['abbreviations']),
            'choice.validated' => $this->choiceCodesText((array) ($choice?->validated_choice_codes ?? [])),
            'choice.validated_abbr' => $this->choiceAbbreviationsText((array) ($choice?->validated_choice_codes ?? []), $data['abbreviations']),
            'choice.omr' => $this->choiceCodesText((array) ($effectiveChoice?->omr_override_choice_codes ?? [])),
            'choice.omr_abbr' => $this->choiceAbbreviationsText((array) ($effectiveChoice?->omr_override_choice_codes ?? []), $data['abbreviations']),
            'choice.effective' => $this->choiceCodesText((array) ($optimized?->final_choice_codes ?? $effectiveChoice?->effective_choice_codes ?? $choice?->validated_choice_codes ?? [])),
            'choice.effective_abbr' => $this->choiceAbbreviationsText((array) ($optimized?->final_choice_codes ?? $effectiveChoice?->effective_choice_codes ?? $choice?->validated_choice_codes ?? []), $data['abbreviations']),
            'merit.common' => $merit?->common_merit_position,
            'merit.general' => $merit?->general_merit_position,
            'merit.technical' => $merit?->technical_merit_position,
            'allocation.cadre' => $allocation?->cadre_code,
            'allocation.cadre_abbr' => (string) ($data['abbreviations']->get((int) ($allocation?->cadre_code ?? 0)) ?? ''),
            'allocation.status' => $allocationStatus,
            'allocation.withheld' => $allocationStatus === 'WITHHELD' ? 'TRUE' : '',
            'allocation.withheld_reason' => $allocationStatus === 'WITHHELD' ? (string) ($disposition?->reason ?? '') : '',
            'allocation.cancelled' => $allocationStatus === 'CANCELLED' ? 'TRUE' : '',
            'allocation.cancelled_reason' => $allocationStatus === 'CANCELLED' ? (string) ($disposition?->reason ?? '') : '',
            'allocation.basis' => $this->uppercaseText($allocation?->allocation_basis),
            'allocation.choice_position' => $allocation?->choice_position,
            'allocation.movement' => $this->uppercaseText($allocation?->movement_type),
            'allocation.merit_position' => $allocation?->merit_position,
            'a5.bachelor' => $this->uppercaseText($a5?->bachelor_status ?? ($allocation ? 'NOT_CHECKED' : 'NOT_APPLICABLE')),
            'a5.prs' => $this->uppercaseText($a5?->prs_status ?? ($allocation ? 'NOT_CHECKED' : 'NOT_APPLICABLE')),
            'a5.technical' => $this->uppercaseText($a5?->technical_status ?? ($allocation ? 'NOT_CHECKED' : 'NOT_APPLICABLE')),
            'a5.quota' => $this->uppercaseText($a5?->quota_status ?? ($allocation ? 'NOT_CHECKED' : 'NOT_APPLICABLE')),
            'a5.overall' => $this->uppercaseText($a5?->overall_status ?? ($allocation ? 'NOT_CHECKED' : 'NOT_ALLOCATED')),
            default => null,
        };
    }

    public function docx(
        AllocationA5Run $a5,
        string $templatePath,
        string $resultDate,
        int $perLine,
        DocxPlaceholderTemplateService $documents,
        ?string $outputPath = null,
        ?string $examName = null,
        ?callable $progress = null,
    ): array {
        $perLine = max(1, min(20, $perLine));
        $separator = '    ';
        $replacements = [];
        $allRegs = collect();
        $cadres = $this->reports->cadres($a5);
        $total = max(1, $cadres->count());

        foreach ($cadres as $row) {
            $query = AllocationA4Result::query()->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
                ->where('cadre_code', (int) $row['code']);
            $allRegs = $allRegs->concat(
                $this->dispositions->applyPublishedOnly($query, $a5, 'allocation_a4_results.registration_id')
                    ->orderBy('merit_position')->orderBy('reg')->pluck('reg')
            );
        }

        foreach ($cadres as $index => $row) {
            $code = (int) $row['code'];
            $abbr = strtoupper((string) $row['abbr']);
            $key = $code.'_'.$abbr;
            $query = AllocationA4Result::query()->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
                ->where('cadre_code', $code);
            $regs = $this->dispositions->applyPublishedOnly($query, $a5, 'allocation_a4_results.registration_id')
                ->orderBy('merit_position')->orderBy('reg')->pluck('reg');
            $replacements[$key] = $regs->isEmpty()
                ? 'NO ALLOCATABLE CANDIDATE WAS LEFT FOR THIS POST'
                : $this->registrationLines($regs->all(), $perLine, $separator);
            $replacements['TOTAL_'.$key] = 'TOTAL = '.number_format($regs->count());
            if ($progress) $progress($index + 1, $total, 'Preparing DOCX placeholder values.');
        }

        $replacements['ALL_ALLOCATED'] = $this->registrationLines($allRegs->all(), $perLine, $separator);
        $replacements['TOTAL_ALLOCATED'] = 'TOTAL = '.number_format($allRegs->count());
        $replacements['EXAM_NAME'] = $examName ?: (string) ($this->context->current()?->name ?? 'Selected Examination');
        $replacements['RESULT_DATE'] = $resultDate;
        $replacements['A5_FINALIZED_DATE'] = $a5->finalized_at?->format('d-m-Y') ?? '';
        $timestamp = now()->format('d-m-Y h:i:s A');
        $path = $outputPath ?: $this->legacyTempPath('docx');
        $summary = $documents->fill($templatePath, $path, $replacements, ['[REPORT_GENERATION_TIMESTAMP]' => $timestamp]);

        return [$path, $this->timestampedDownloadName($this->examSlug($examName).'-final-allocation', 'docx'), $summary];
    }

    public function audit(
        AllocationA5Run $a5,
        string $type,
        string $scope,
        ?int $cadre,
        array $parameters,
        string $path,
        string $name,
        ?int $actorId,
    ): void {
        AllocationA6ExportAudit::query()->create([
            'allocation_a5_run_id' => $a5->id,
            'export_type' => $type,
            'scope' => $scope,
            'cadre_code' => $cadre,
            'parameters' => $parameters,
            'a4_output_hash' => $a5->a4_output_hash,
            'a5_candidate_hash' => $a5->candidate_result_hash,
            'a5_capacity_hash' => $a5->capacity_result_hash,
            'file_name' => $name,
            'file_hash' => hash_file('sha256', $path) ?: null,
            'generated_by' => $actorId,
            'generated_at' => now(),
        ]);
    }

    public function queuedOutputPath(int $runId, string $extension): string
    {
        return $this->files->outputPath('allocation-a6', $runId, $extension);
    }

    private function registrationLines(array $regs, int $perLine, string $separator): string
    {
        $chunks = array_chunk(array_values(array_map('strval', $regs)), $perLine);
        return implode("\n", array_map(fn ($chunk) => implode($separator, $chunk), $chunks));
    }


    private function timestampedDownloadName(string $baseName, string $extension): string
    {
        return $baseName.'-'.now()->format('Ymd-His').'.'.ltrim($extension, '.');
    }

    private function legacyTempPath(string $extension): string
    {
        $directory = storage_path('app/private/allocation-a6-exports');
        File::ensureDirectoryExists($directory);
        return $directory.DIRECTORY_SEPARATOR.uniqid('a6-', true).'.'.$extension;
    }

    private function examSlug(?string $examName = null): string
    {
        return Str::slug($examName ?: (string) ($this->context->current()?->name ?? 'allocation-report')) ?: 'allocation-report';
    }

    private function choiceCodesText(array $codes): string
    {
        return implode(' ', array_map('strval', array_values(array_filter($codes, fn ($value) => filled($value)))));
    }

    private function choiceAbbreviationsText(array $codes, Collection $abbreviations): string
    {
        return collect($codes)->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (string) ($abbreviations->get((int) $value) ?? 'UNMAPPED'))
            ->implode(' ');
    }

    private function uppercaseText(mixed $value): string
    {
        return strtoupper(trim((string) ($value ?? '')));
    }

    private function enumValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if ($value instanceof UnitEnum) {
            return $value->name;
        }
        return $value;
    }
}
