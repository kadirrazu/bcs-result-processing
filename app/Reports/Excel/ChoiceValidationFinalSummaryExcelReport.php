<?php

namespace App\Reports\Excel;

use App\Enums\CadreCategory;
use App\Services\ChoiceValidation\ChoiceValidationFinalizedDatasetService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class ChoiceValidationFinalSummaryExcelReport
{
    public function __construct(
        private readonly ChoiceValidationFinalizedDatasetService $dataset,
        private readonly ExaminationContext $examinationContext,
    ) {}

    /** @return array{content:string,filename:string} */
    public function generate(): array
    {
        // One integrity scan only for the whole export request.
        $summary = $this->dataset->verifiedSummary();
        $version = (int) $summary['validation_version'];

        $exam = $this->examinationContext->current();
        $examName = $exam?->name
            ?: (($exam?->bcs_number ? $exam->bcs_number.' BCS Examination' : null)
                ?: 'BCS Examination');

        $book = new Spreadsheet();

        $overview = $book->getActiveSheet();
        $overview->setTitle('Summary');
        $overview->fromArray([
            ['Exam Name', $examName],
            ['Report Title', 'Final Choice Validation Summary'],
            ['Validation Version', $summary['validation_version']],
            ['Source Version', $summary['source_version']],
            ['Circular Version', $summary['circular_version']],
            ['Status', 'FINALIZED'],
            ['Total Candidates', $summary['total_candidates']],
            ['Valid Candidates', $summary['valid_candidates']],
            ['Not Applicable', $summary['not_applicable_candidates']],
            ['No Valid Choice', $summary['zero_valid_choice_candidates']],
            ['Kept Choices', $summary['kept_choices']],
            ['Removed Choices', $summary['removed_choices']],
            ['Expanded Choices', $summary['expanded_choices']],
            ['Dataset Hash', $summary['dataset_hash']],
            ['Finalized By', $summary['finalized_by_name']],
            ['Finalized At', optional($summary['finalized_at'])->format('Y-m-d H:i:s')],
            ['Finalization Note', $summary['finalization_note']],
        ], null, 'A1');
        $overview->getColumnDimension('A')->setWidth(28);
        $overview->getColumnDimension('B')->setWidth(75);
        $overview->getStyle('A1:A17')->getFont()->setBold(true);

        $sheet = $book->createSheet();
        $sheet->setTitle('Finalized Choices');
        $sheet->fromArray([[
            'Reg',
            'User',
            'Candidate Name',
            'Original Category',
            'Written Derived Category',
            'Current Track',
            'Status',
            'Reason Code',
            'Original Choices',
            'Validated Choices',
            'Original Count',
            'Validated Count',
            'Removed Count',
            'Expanded Count',
        ]], null, 'A1');

        $excelRow = 2;

        DB::connection('exam')
            ->table('choice_validation_results as r')
            ->leftJoin('registrations as reg', 'reg.id', '=', 'r.registration_id')
            ->leftJoin('choice_validation_sources as src', 'src.id', '=', 'r.choice_source_id')
            ->where('r.validation_version', $version)
            ->select([
                'r.id',
                'r.reg',
                'r.user_id',
                'r.written_qualified_track',
                'r.effective_track',
                'r.status',
                'r.result_reason_code',
                'r.validated_choice_codes',
                'r.original_choice_count',
                'r.validated_choice_count',
                'r.removed_choice_count',
                'r.expanded_choice_count',
                'reg.name as candidate_name',
                'reg.cadre_category as registration_category',
                'src.source_snapshot',
            ])
            ->orderBy('r.id')
            ->chunkById(
                500,
                function ($rows) use ($sheet, &$excelRow): void {
                    $matrix = [];

                    foreach ($rows as $row) {
                        $source = $this->decodeJson($row->source_snapshot);
                        $validated = $this->decodeJson($row->validated_choice_codes);

                        $matrix[] = [
                            $row->reg,
                            $row->user_id,
                            $row->candidate_name,
                            $this->categoryCode($row->registration_category),
                            $row->written_qualified_track,
                            $row->effective_track,
                            $row->status,
                            $row->result_reason_code,
                            $this->originalChoiceText($source),
                            implode(' ', array_values($validated)),
                            (int) $row->original_choice_count,
                            (int) $row->validated_choice_count,
                            (int) $row->removed_choice_count,
                            (int) $row->expanded_choice_count,
                        ];
                    }

                    if ($matrix !== []) {
                        $sheet->fromArray($matrix, null, 'A'.$excelRow);
                        $excelRow += count($matrix);
                    }
                },
                'r.id',
                'id'
            );

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:N'.max(1, $excelRow - 1));
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);

        // AutoSize performs an expensive content measurement pass over every cell.
        // Fixed practical widths keep exports predictable for thousands of rows.
        foreach ([
            'A' => 14, 'B' => 18, 'C' => 28, 'D' => 16, 'E' => 22,
            'F' => 16, 'G' => 34, 'H' => 34, 'I' => 58, 'J' => 58,
            'K' => 14, 'L' => 14, 'M' => 14, 'N' => 14,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $path = tempnam(sys_get_temp_dir(), 'choice-validation-final-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary Choice Validation Excel file.');
        }

        try {
            $writer = new Xlsx($book);
            $writer->setPreCalculateFormulas(false);
            $writer->save($path);

            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException('Unable to read generated Choice Validation Excel report.');
            }
        } finally {
            $book->disconnectWorksheets();
            @unlink($path);
        }

        return [
            'content' => $content,
            'filename' => "choice-validation-final-v{$version}-".now()->format('Ymd-His').'.xlsx',
        ];
    }

    /** @return array<mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function categoryCode(mixed $value): string
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false) {
            return (string) ($value ?? '');
        }

        return CadreCategory::tryFrom((int) $integer)?->code() ?? (string) $value;
    }

    /** @param array<string,mixed> $source */
    private function originalChoiceText(array $source): string
    {
        $choices = [];

        foreach ($source as $column => $value) {
            if (! preg_match('/^opt_(\d+)$/', (string) $column, $matches)) {
                continue;
            }

            $value = trim((string) ($value ?? ''));
            if ($value === '') {
                continue;
            }

            $choices[(int) $matches[1]] = $value;
        }

        ksort($choices);

        return implode(' ', $choices);
    }
}
