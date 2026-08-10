<?php

namespace App\Reports\Excel;

use App\Services\Circular\CircularFinalizedDatasetService;
use App\Support\Examinations\ExaminationContext;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class CircularFinalSummaryExcelReport
{
    public function __construct(
        private readonly CircularFinalizedDatasetService $dataset,
        private readonly ExaminationContext $examinationContext,
    ) {}

    /** @return array{content:string,filename:string} */
    public function generate(): array
    {
        $version = $this->dataset->finalizedVersion();
        $entries = $this->dataset->entries();
        $summary = $this->dataset->summary();
        $exam = $this->examinationContext->current();
        $examName = $exam?->name ?: (($exam?->bcs_number ? $exam->bcs_number.' BCS Examination' : null) ?: 'BCS Examination');

        $book = new Spreadsheet();
        $overview = $book->getActiveSheet();
        $overview->setTitle('Summary');
        $overview->fromArray([
            ['Exam Name', $examName],
            ['Report Title', 'Final Circular Summary'],
            ['Circular Version', $version],
            ['Status', 'FINALIZED'],
            ['Total Circular Entries', $summary['entry_count']],
            ['Active Entries', $summary['active_entry_count']],
            ['General Entries', $summary['general_entry_count']],
            ['Technical Entries', $summary['technical_entry_count']],
            ['General Posts', $summary['general_posts']],
            ['Technical Posts', $summary['technical_posts']],
            ['Total Approved Posts', $summary['total_posts']],
            ['Confirmed At', optional($summary['confirmed_at'])->format('Y-m-d H:i:s')],
            ['Finalized At', optional($summary['finalized_at'])->format('Y-m-d H:i:s')],
            ['Confirmation Notes', $summary['confirmation_notes']],
        ], null, 'A1');
        $overview->getColumnDimension('A')->setWidth(28);
        $overview->getColumnDimension('B')->setWidth(70);
        $overview->getStyle('A1:A14')->getFont()->setBold(true);

        $sheet = $book->createSheet();
        $sheet->setTitle('Circular Entries');
        $sheet->fromArray([[ 
            'Serial', 'Cadre Name', 'Cadre Name (Bangla)', 'Post Name', 'Post Name (Bangla)',
            'Code', 'Cadre Type', 'Posts', 'Bachelor Subject Codes', 'PRS Codes', 'Status', 'Note'
        ]], null, 'A1');

        $row = 2;
        foreach ($entries as $entry) {
            $serial = (string) $entry->cadre_serial.($entry->sub_serial !== null ? '.'.$entry->sub_serial : '');
            $sheet->fromArray([[
                $serial,
                $entry->cadre_name_snapshot,
                $entry->cadre_name_bn_snapshot,
                $entry->post_name_snapshot,
                $entry->post_name_bn_snapshot,
                $entry->effective_code,
                $entry->cadre_type->value,
                $entry->post_count,
                $entry->bachelorSubjects->pluck('subject_code')->implode('|'),
                $entry->prsSubjects->pluck('prs_code')->implode('|'),
                strtoupper((string) $entry->status),
                $entry->note,
            ]], null, 'A'.$row);
            $row++;
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:L'.max(1, $row - 1));
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'circular-final-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary Excel report file.');
        }

        try {
            (new Xlsx($book))->save($path);
            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException('Unable to read generated Excel report.');
            }
        } finally {
            $book->disconnectWorksheets();
            @unlink($path);
        }

        return [
            'content' => $content,
            'filename' => "circular-final-summary-v{$version}-".now()->format('Ymd-His').'.xlsx',
        ];
    }
}
