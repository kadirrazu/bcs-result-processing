<?php

namespace App\Services\Allocation;

use App\Models\AllocationA4Result;
use App\Models\AllocationA5Run;
use App\Models\AllocationA6ExportAudit;
use App\Models\Registration;
use App\Models\TabulationResult;
use App\Models\PreliminaryResult;
use App\Models\WrittenResult;
use App\Models\VivaResult;
use App\Models\MeritResult;
use App\Models\ChoiceValidationResult;
use App\Models\ChoiceOptimizationHistoricalChoice;
use App\Support\Examinations\ExaminationContext;
use App\Services\Documents\DocxPlaceholderTemplateService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use ZipArchive;

/**
 * A6 publishing/export engine. It reads exact A5-bound A4 results and current
 * finalized Tabulation/Merit sources; it never changes allocation evidence.
 */
final class AllocationA6ExportService
{
    public function __construct(
        private readonly AllocationA6ReportService $reports,
        private readonly ExaminationContext $context,
    ) {}

    public function consolidatedTxt(AllocationA5Run $a5, int $perLine, string $reportTitle): array
    {
        $perLine = max(1, min(20, $perLine));
        $path = $this->tempPath('txt');
        File::put($path, $this->txtContent($a5, $perLine, $reportTitle, null));
        return [$path, $this->examSlug().'-final-allocation.txt'];
    }

    public function cadreTxtZip(AllocationA5Run $a5, int $perLine, string $reportTitle): array
    {
        if (! class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZIP extension is required.');
        $perLine = max(1, min(20, $perLine));
        $zipPath = $this->tempPath('zip');
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create ZIP.');
        foreach ($this->reports->cadres($a5) as $row) {
            if ((int) $row['allocated'] < 1) continue;
            $code = (int) $row['code']; $abbr = (string) $row['abbr'];
            $zip->addFromString($code.'-'.$abbr.'.txt', $this->txtContent($a5, $perLine, $reportTitle, $code));
        }
        $zip->close();
        return [$zipPath, $this->examSlug().'-cadre-wise-final-allocation-txt.zip'];
    }

    private function txtContent(AllocationA5Run $a5, int $perLine, string $reportTitle, ?int $onlyCadre): string
    {
        $exam = (string) ($this->context->current()?->name ?? 'Selected Examination');
        $lines = [
            'Exam Title: '.$exam,
            'Report Title: '.$reportTitle,
            'Generation Time: '.now()->format('d-m-Y h:i:s A'),
            '',
        ];
        foreach ($this->reports->cadres($a5) as $row) {
            $code = (int) $row['code'];
            if ($onlyCadre !== null && $code !== $onlyCadre) continue;
            $regs = AllocationA4Result::query()
                ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
                ->where('cadre_code', $code)
                ->orderBy('merit_position')->orderBy('reg')->pluck('reg');
            if ($regs->isEmpty()) continue;
            $lines[] = $code.' - '.(string) $row['abbr'];
            $lines[] = '';
            foreach ($regs->chunk($perLine) as $chunk) $lines[] = $chunk->implode(' ');
            $lines[] = '';
        }
        return implode("\r\n", $lines)."\r\n";
    }

    /** @return array{0:string,1:string} */
    public function xlsx(AllocationA5Run $a5, string $scope, ?int $cadreCode = null): array
    {
        $scope = in_array($scope, ['tabulation_eligible','allocated','cadre'], true) ? $scope : 'tabulation_eligible';
        if ($scope === 'cadre' && ! $cadreCode) throw new RuntimeException('Cadre code is required.');

        $rows = $scope === 'tabulation_eligible'
            ? $this->reports->tabulationEligibleQuery()->orderBy('reg')->get()
            : AllocationA4Result::query()->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
                ->when($scope === 'cadre', fn ($q) => $q->where('cadre_code', $cadreCode))
                ->orderBy($scope === 'cadre' ? 'merit_position' : 'cadre_code')->orderBy('merit_position')->orderBy('reg')->get();

        // Preload each authoritative module once. Export must remain practical for
        // the full BCS population and must not issue per-candidate N+1 queries.
        $registrationIds = $rows->pluck('registration_id')->map(fn($v)=>(int)$v)->unique()->values();
        $registrations = Registration::query()->whereIn('id',$registrationIds)->get()->keyBy('id');
        $preliminary = PreliminaryResult::query()->whereIn('registration_id',$registrationIds)->get()->keyBy('registration_id');
        $written = WrittenResult::query()->whereIn('registration_id',$registrationIds)->get()->keyBy('registration_id');
        $viva = VivaResult::query()->whereIn('registration_id',$registrationIds)->get()->keyBy('registration_id');
        $tabRunId = $this->reports->currentTabulationRunId();
        $tabulations = $tabRunId ? TabulationResult::query()->where('processing_run_id',$tabRunId)->whereIn('registration_id',$registrationIds)->get()->keyBy('registration_id') : collect();
        $meritRunId = $this->reports->currentMeritRunId();
        $merits = $meritRunId ? MeritResult::query()->where('processing_run_id',$meritRunId)->whereIn('registration_id',$registrationIds)->get()->keyBy('registration_id') : collect();
        $choices = ChoiceValidationResult::query()->whereIn('registration_id',$registrationIds)->orderBy('validation_version')->get()->keyBy('registration_id');
        $optimized = ChoiceOptimizationHistoricalChoice::query()->whereIn('registration_id',$registrationIds)->orderBy('id')->get()->keyBy('registration_id');
        $allocations = AllocationA4Result::query()->where('allocation_a4_run_id',(int)$a5->allocation_a4_run_id)->whereIn('registration_id',$registrationIds)->get()->keyBy('registration_id');
        $a5Results = $a5->candidateResults()->whereIn('registration_id',$registrationIds)->get()->keyBy('registration_id');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Final Report');
        $headers = [
            'SL','Reg','User ID','Name','Father Name','Mother Name','DOB','Sex','District','Cadre Category',
            'Bachelor Subject','PRS','CFF','EM','PHC',
            'Preliminary Mark','Preliminary Result',
            'Written Qualified Track','General Written','Technical Written','General Written Result','Technical Written Result',
            'Viva Mark','Viva Result',
            'General Grand Total','Technical Grand Total','General Merit Eligible','Technical Merit Eligible',
            'Validated Choices','Optimized Final Choices',
            'Common Merit Position','General Merit Position','Technical Merit Position',
            'Allocated Cadre','Choice Position','Allocation Basis','Movement','Cadre Merit Position',
            'A5 Bachelor','A5 PRS','A5 Technical','A5 Quota','A5 Overall',
        ];
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$lastColumn.'1');

        $r = 2; $sl = 1;
        foreach ($rows as $source) {
            $currentRow = $r;
            $regId = (int) $source->registration_id;
            $reg = $registrations->get($regId);
            $pre = $preliminary->get($regId); $wr = $written->get($regId); $vi = $viva->get($regId);
            $tab = $tabulations->get($regId); $merit = $merits->get($regId); $choice = $choices->get($regId); $opt = $optimized->get($regId);
            $allocation = $allocations->get($regId); $a5Result = $a5Results->get($regId);
            $sheet->fromArray([[
                $sl++, (string)($reg?->reg ?? $source->reg), (string)($reg?->user_id ?? ''), (string)($reg?->name ?? ''),
                (string)($reg?->father_name ?? ''), (string)($reg?->mother_name ?? ''), $reg?->birth_date?->format('Y-m-d'), (string)($reg?->sex_code ?? ''), (string)($reg?->district_code ?? ''),
                $reg?->cadre_category?->code() ?? '', (string)($reg?->bachelor_subject_code ?? ''), (string)($reg?->post_related_subject_code ?? ''),
                $reg?->has_ff_quota ? 'YES':'NO', $reg?->has_em_quota ? 'YES':'NO', $reg?->has_phc_quota ? 'YES':'NO',
                $pre?->mark, $pre?->result_status?->value ?? $pre?->result_status,
                $wr?->written_qualified_track?->value ?? $wr?->written_qualified_track, $wr?->general_counted_total, $wr?->technical_counted_total,
                $wr?->general_result_status?->value ?? $wr?->general_result_status, $wr?->technical_result_status?->value ?? $wr?->technical_result_status,
                $vi?->mark, $vi?->viva_result_status?->value ?? $vi?->viva_result_status,
                $tab?->general_grand_total, $tab?->technical_grand_total, $tab?->general_merit_eligible ? 'YES':'NO', $tab?->technical_merit_eligible ? 'YES':'NO',
                implode(' ',array_map('strval',(array)($choice?->validated_choice_codes ?? []))), implode(' ',array_map('strval',(array)($opt?->final_choice_codes ?? []))),
                $merit?->common_merit_position, $merit?->general_merit_position, $merit?->technical_merit_position,
                $allocation?->cadre_code, $allocation?->choice_position, $allocation?->allocation_basis, $allocation?->movement_type, $allocation?->merit_position,
                $a5Result?->bachelor_status ?? ($allocation?'NOT_CHECKED':'NOT_APPLICABLE'), $a5Result?->prs_status ?? ($allocation?'NOT_CHECKED':'NOT_APPLICABLE'),
                $a5Result?->technical_status ?? ($allocation?'NOT_CHECKED':'NOT_APPLICABLE'), $a5Result?->quota_status ?? ($allocation?'NOT_CHECKED':'NOT_APPLICABLE'),
                $a5Result?->overall_status ?? ($allocation?'NOT_CHECKED':'NOT_ALLOCATED'),
            ]], null, 'A'.$r++);
            // Registration/User identifiers are identifiers, not numbers. Keep
            // leading zeroes intact in Excel.
            $sheet->setCellValueExplicit('B'.$currentRow, (string)($reg?->reg ?? $source->reg), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$currentRow, (string)($reg?->user_id ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        for ($i=1; $i<=count($headers); $i++) $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        $path = $this->tempPath('xlsx');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $suffix = $scope === 'cadre' ? 'cadre-'.$cadreCode : str_replace('_','-',$scope);
        return [$path, $this->examSlug().'-'.$suffix.'.xlsx'];
    }

    public function docx(
        AllocationA5Run $a5,
        string $templatePath,
        string $resultDate,
        int $perLine,
        DocxPlaceholderTemplateService $documents,
    ): array {
        $perLine = max(1, min(20, $perLine));
        $separator = '    ';
        $replacements = [];
        $allRegs = collect();
        foreach ($this->reports->cadres($a5) as $cadreRow) {
            $allRegs = $allRegs->concat(AllocationA4Result::query()->where('allocation_a4_run_id',(int)$a5->allocation_a4_run_id)
                ->where('cadre_code',(int)$cadreRow['code'])->orderBy('merit_position')->orderBy('reg')->pluck('reg'));
        }
        foreach ($this->reports->cadres($a5) as $row) {
            $code = (int)$row['code']; $abbr = strtoupper((string)$row['abbr']);
            $key = $code.'_'.$abbr;
            $regs = AllocationA4Result::query()->where('allocation_a4_run_id',(int)$a5->allocation_a4_run_id)->where('cadre_code',$code)->orderBy('merit_position')->orderBy('reg')->pluck('reg');
            $replacements[$key] = $this->registrationLines($regs->all(), $perLine, $separator);
            $replacements['TOTAL_'.$key] = (string)$regs->count();
        }
        $replacements['ALL_ALLOCATED'] = $this->registrationLines($allRegs->all(), $perLine, $separator);
        $replacements['TOTAL_ALLOCATED'] = (string)$allRegs->count();
        $replacements['EXAM_NAME'] = (string)($this->context->current()?->name ?? 'Selected Examination');
        $replacements['RESULT_DATE'] = $resultDate;
        $replacements['A5_FINALIZED_DATE'] = $a5->finalized_at?->format('d-m-Y') ?? '';
        $timestamp = now()->format('d-m-Y h:i:s A');
        $path = $this->tempPath('docx');
        $summary = $documents->fill($templatePath, $path, $replacements, ['[REPORT_GENERATION_TIMESTAMP]' => $timestamp]);
        return [$path, $this->examSlug().'-final-allocation-'.now()->format('Ymd-His').'.docx', $summary];
    }

    public function audit(AllocationA5Run $a5, string $type, string $scope, ?int $cadre, array $parameters, string $path, string $name, ?int $actorId): void
    {
        AllocationA6ExportAudit::query()->create([
            'allocation_a5_run_id'=>$a5->id,'export_type'=>$type,'scope'=>$scope,'cadre_code'=>$cadre,
            'parameters'=>$parameters,'a4_output_hash'=>$a5->a4_output_hash,'a5_candidate_hash'=>$a5->candidate_result_hash,
            'a5_capacity_hash'=>$a5->capacity_result_hash,'file_name'=>$name,'file_hash'=>hash_file('sha256',$path) ?: null,
            'generated_by'=>$actorId,'generated_at'=>now(),
        ]);
    }

    private function registrationLines(array $regs, int $perLine, string $separator): string
    {
        $chunks = array_chunk(array_values(array_map('strval',$regs)), $perLine);
        return implode("\n", array_map(fn($c) => implode($separator,$c), $chunks));
    }

    private function tempPath(string $ext): string
    {
        $dir = storage_path('app/private/allocation-a6-exports'); File::ensureDirectoryExists($dir);
        return $dir.DIRECTORY_SEPARATOR.uniqid('a6-', true).'.'.$ext;
    }

    private function examSlug(): string
    {
        return Str::slug((string)($this->context->current()?->name ?? 'allocation-report')) ?: 'allocation-report';
    }
}
