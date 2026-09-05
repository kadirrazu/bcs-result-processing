<?php

namespace App\Jobs;

use App\Models\AllocationA5Run;
use App\Models\Examination;
use App\Models\ReportingExportRun;
use App\Reports\Pdf\AllocationA6SummaryPdfReport;
use App\Services\Allocation\AllocationA6ExportService;
use App\Services\Allocation\AllocationA6ReadinessService;
use App\Services\Documents\DocxPlaceholderTemplateService;
use App\Services\Reporting\ReportExportFileStore;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * A6 orchestration only. Generic export-run state and output storage are shared,
 * while Allocation readiness, authoritative source binding and field sourcing
 * stay inside the A6 layer.
 */
final class ProcessAllocationA6Export implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $examinationId,
        public readonly int $exportRunId,
        public readonly int $a5RunId,
        public readonly ?int $actorId,
    ) {
        $this->onQueue((string) config('allocation.queue', 'imports'));
    }

    public function handle(
        ExaminationConnectionManager $connections,
        AllocationA6ReadinessService $readiness,
        AllocationA6ExportService $exports,
        AllocationA6SummaryPdfReport $summaryPdf,
        DocxPlaceholderTemplateService $documents,
        ReportExportFileStore $files,
    ): void {
        $exam = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($exam);

        try {
            $run = ReportingExportRun::query()
                ->where('module', 'allocation_a6')
                ->findOrFail($this->exportRunId);

            $this->update($run, 'running', 'VERIFYING_SOURCE', 3, 'Confirming latest finalized A4/A5 report source.');
            $a5 = $readiness->requireReadyStrict();
            $this->assertFrozenSource($run, $a5);

            $parameters = (array) $run->parameters;
            $progress = function (int $current, int $total, string $message) use ($run): void {
                $total = max(1, $total);
                $percent = 10 + (int) floor(min(1, $current / $total) * 78);
                ReportingExportRun::query()->whereKey($run->id)->update([
                    'phase' => 'GENERATING',
                    'progress_percent' => min(88, $percent),
                    'progress_current' => max(0, $current),
                    'progress_total' => max(0, $total),
                    'progress_message' => $message,
                ]);
            };

            $this->update($run, 'running', 'GENERATING', 10, 'Generating export file from frozen A5-bound Allocation results.');
            [$path, $name, $mime] = match ($run->export_type) {
                'TXT' => $this->generateTxt($run, $a5, $exports, (string) $exam->name, $parameters, $progress),
                'XLSX' => $this->generateXlsx($run, $a5, $exports, (string) $exam->name, $parameters, $progress),
                'PDF' => $this->generatePdf($run, $a5, $exports, $summaryPdf, (string) $exam->name),
                'DOCX' => $this->generateDocx($run, $a5, $exports, $documents, (string) $exam->name, $parameters, $progress),
                default => throw new RuntimeException('Unsupported A6 export type.'),
            };

            $this->update($run, 'running', 'AUDITING', 94, 'Hashing file and writing export provenance.');
            $cadreCode = isset($parameters['cadre_code']) && (int) $parameters['cadre_code'] > 0 ? (int) $parameters['cadre_code'] : null;
            $auditParameters = $parameters;
            unset($auditParameters['template_path']);
            $exports->audit(
                $a5,
                (string) $run->export_type,
                (string) ($run->scope ?? ''),
                $cadreCode,
                $auditParameters,
                $path,
                $name,
                $this->actorId,
            );

            $hash = hash_file('sha256', $path) ?: null;
            ReportingExportRun::query()->whereKey($run->id)->update([
                'status' => 'completed',
                'phase' => 'COMPLETED',
                'progress_percent' => 100,
                'progress_message' => 'Export completed and is ready to download.',
                'file_path' => $path,
                'file_name' => $name,
                'file_mime' => $mime,
                'file_hash' => $hash,
                'completed_at' => now(),
                'failure_message' => null,
            ]);

            if ($run->export_type === 'DOCX') {
                $files->forget($parameters['template_path'] ?? null);
            }
        } catch (Throwable $e) {
            ReportingExportRun::query()->whereKey($this->exportRunId)->update([
                'status' => 'failed',
                'phase' => 'FAILED',
                'progress_message' => 'Export generation failed. No result file was published.',
                'failure_message' => mb_substr($e->getMessage(), 0, 65000),
                'completed_at' => now(),
            ]);
            throw $e;
        } finally {
            $connections->disconnect();
        }
    }

    private function generateTxt(
        ReportingExportRun $run,
        AllocationA5Run $a5,
        AllocationA6ExportService $exports,
        string $examName,
        array $parameters,
        callable $progress,
    ): array {
        $mode = (string) ($parameters['mode'] ?? 'consolidated');
        $perLine = (int) ($parameters['registrations_per_line'] ?? 8);
        $title = (string) ($parameters['report_title'] ?? 'Final Cadre Allocation');
        $extension = $mode === 'cadre_zip' ? 'zip' : 'txt';
        $path = $exports->queuedOutputPath($run->id, $extension);
        [$path, $name] = $mode === 'cadre_zip'
            ? $exports->cadreTxtZip($a5, $perLine, $title, $path, $examName, $progress)
            : $exports->consolidatedTxt($a5, $perLine, $title, $path, $examName, $progress);

        return [$path, $name, $mode === 'cadre_zip' ? 'application/zip' : 'text/plain; charset=UTF-8'];
    }

    private function generateXlsx(
        ReportingExportRun $run,
        AllocationA5Run $a5,
        AllocationA6ExportService $exports,
        string $examName,
        array $parameters,
        callable $progress,
    ): array {
        $scope = (string) ($run->scope ?: 'tabulation_eligible');
        $cadre = isset($parameters['cadre_code']) && (int) $parameters['cadre_code'] > 0 ? (int) $parameters['cadre_code'] : null;
        $path = $exports->queuedOutputPath($run->id, 'xlsx');
        $fields = array_values((array) ($parameters['selected_fields'] ?? []));

        if ($scope === 'allocation_summary') {
            [$path, $name] = $exports->allocationSummaryXlsx($a5, $path, $examName, $progress);
        } elseif ($scope === 'allocation_summary_short') {
            [$path, $name] = $exports->allocationShortSummaryXlsx($a5, $path, $examName, $progress);
        } else {
            [$path, $name] = $fields !== []
                ? $exports->dynamicXlsx($a5, $scope, $cadre, $fields, $path, $examName, $progress)
                : $exports->xlsx($a5, $scope, $cadre, $path, $examName, $progress);
        }

        return [$path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    }

    private function generatePdf(
        ReportingExportRun $run,
        AllocationA5Run $a5,
        AllocationA6ExportService $exports,
        AllocationA6SummaryPdfReport $summaryPdf,
        string $examName,
    ): array {
        $scope = (string) $run->scope;
        if (! in_array($scope, ['allocation_summary', 'allocation_summary_short'], true)) {
            throw new RuntimeException('Unsupported A6 PDF report scope.');
        }

        $path = $exports->queuedOutputPath($run->id, 'pdf');
        $generated = $summaryPdf->generate($a5, $examName, $scope === 'allocation_summary_short');
        File::put($path, $generated['content']);

        return [$path, $generated['filename'], 'application/pdf'];
    }

    private function generateDocx(
        ReportingExportRun $run,
        AllocationA5Run $a5,
        AllocationA6ExportService $exports,
        DocxPlaceholderTemplateService $documents,
        string $examName,
        array $parameters,
        callable $progress,
    ): array {
        $templatePath = (string) ($parameters['template_path'] ?? '');
        if ($templatePath === '' || ! is_file($templatePath)) {
            throw new RuntimeException('Queued DOCX template file is missing.');
        }
        $path = $exports->queuedOutputPath($run->id, 'docx');
        [$path, $name] = $exports->docx(
            $a5,
            $templatePath,
            (string) ($parameters['result_date'] ?? now()->format('d-m-Y')),
            (int) ($parameters['registrations_per_line'] ?? 8),
            $documents,
            $path,
            $examName,
            $progress,
        );

        return [$path, $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    }

    private function assertFrozenSource(ReportingExportRun $run, AllocationA5Run $a5): void
    {
        $snapshot = (array) $run->source_snapshot;
        if ((int) ($snapshot['allocation_a5_run_id'] ?? 0) !== (int) $a5->id || (int) $a5->id !== $this->a5RunId) {
            throw new RuntimeException('A6_EXPORT_SOURCE_CHANGED: Current finalized A5 run differs from the queued export source.');
        }

        foreach ([
            'a4_output_hash' => (string) $a5->a4_output_hash,
            'a5_candidate_hash' => (string) $a5->candidate_result_hash,
            'a5_capacity_hash' => (string) $a5->capacity_result_hash,
        ] as $key => $actual) {
            $stored = (string) ($snapshot[$key] ?? '');
            if ($stored === '' || $actual === '' || ! hash_equals($stored, $actual)) {
                throw new RuntimeException('A6_EXPORT_SOURCE_HASH_MISMATCH: '.$key.' changed after export was queued.');
            }
        }
    }

    private function update(ReportingExportRun $run, string $status, string $phase, int $percent, string $message): void
    {
        ReportingExportRun::query()->whereKey($run->id)->update([
            'status' => $status,
            'phase' => $phase,
            'progress_percent' => max(0, min(100, $percent)),
            'progress_message' => $message,
            'started_at' => $run->started_at ?: now(),
            'failure_message' => null,
        ]);
    }
}
