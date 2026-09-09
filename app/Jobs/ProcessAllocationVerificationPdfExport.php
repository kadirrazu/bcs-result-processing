<?php

namespace App\Jobs;

use App\Models\AllocationA5Run;
use App\Models\Examination;
use App\Models\ReportingExportRun;
use App\Reports\Pdf\AllocationVerificationPdfReport;
use App\Services\Allocation\AllocationA6ReadinessService;
use App\Services\Allocation\AllocationResultDispositionService;
use App\Services\Reporting\AllocationVerificationReportService;
use App\Services\Reporting\ReportExportFileStore;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class ProcessAllocationVerificationPdfExport implements ShouldQueue
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
        AllocationResultDispositionService $dispositions,
        AllocationVerificationReportService $reports,
        AllocationVerificationPdfReport $pdf,
        ReportExportFileStore $files,
    ): void {
        $exam = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($exam);

        try {
            $run = ReportingExportRun::query()
                ->where('module', 'cadre_verification')
                ->where('export_type', 'PDF')
                ->findOrFail($this->exportRunId);

            $this->update($run, 'running', 'VERIFYING_SOURCE', 5, 'Confirming current finalized A5 and A5.5 publication state.');

            $a5 = $readiness->requireReadyStrict();
            $this->assertFrozenSource($run, $a5, $dispositions);

            $parameters = (array) $run->parameters;
            $type = (string) ($parameters['report_type'] ?? $run->scope ?? '');
            $cadreCode = isset($parameters['cadre_code']) && (int) $parameters['cadre_code'] > 0
                ? (int) $parameters['cadre_code']
                : null;

            $this->update($run, 'running', 'PREPARING_REPORT', 20, 'Preparing identity-free verification report rows.');

            if ($type === 'cadre-serial-merit') {
                $data = $reports->buildCadreSerialMerit($a5);
                $rowCount = (int) $data['sections']->sum('total_allocated');

                $this->update(
                    $run,
                    'running',
                    'RENDERING_PDF',
                    55,
                    'Rendering '.number_format($rowCount).' ACTIVE allocated candidates to A4-Landscape PDF.',
                    $rowCount,
                    $rowCount,
                );

                $generated = $pdf->generateCadreSerialMerit(
                    $data,
                    (string) $exam->name
                );
            } else {
                $data = $reports->build($type, $a5, $cadreCode);
                $rowCount = (int) $data['rows']->count();

                $this->update(
                    $run,
                    'running',
                    'RENDERING_PDF',
                    55,
                    'Rendering '.number_format($rowCount).' report rows to Legal-Landscape PDF.',
                    $rowCount,
                    $rowCount,
                );

                $generated = $pdf->generate($data, (string) $exam->name, $type, $cadreCode);
            }
            $path = $files->outputPath('cadre-verification', (int) $run->id, 'pdf');
            File::put($path, $generated['content']);

            $this->update($run, 'running', 'FINALIZING', 92, 'Hashing and finalizing generated PDF.', $rowCount, $rowCount);

            ReportingExportRun::query()->whereKey($run->id)->update([
                'status' => 'completed',
                'phase' => 'COMPLETED',
                'progress_percent' => 100,
                'progress_current' => $rowCount,
                'progress_total' => $rowCount,
                'progress_message' => 'Verification PDF completed and is ready to download.',
                'file_path' => $path,
                'file_name' => (string) $generated['filename'],
                'file_mime' => 'application/pdf',
                'file_hash' => hash_file('sha256', $path) ?: null,
                'completed_at' => now(),
                'failure_message' => null,
            ]);
        } catch (Throwable $e) {
            ReportingExportRun::query()->whereKey($this->exportRunId)->update([
                'status' => 'failed',
                'phase' => 'FAILED',
                'progress_message' => 'Verification PDF generation failed. No PDF was published.',
                'failure_message' => mb_substr($e->getMessage(), 0, 65000),
                'completed_at' => now(),
            ]);

            throw $e;
        } finally {
            $connections->disconnect();
        }
    }

    private function assertFrozenSource(
        ReportingExportRun $run,
        AllocationA5Run $a5,
        AllocationResultDispositionService $dispositions,
    ): void {
        if ((int) $a5->id !== $this->a5RunId) {
            throw new RuntimeException('Verification PDF source A5 changed before generation.');
        }

        $snapshot = (array) $run->source_snapshot;

        if ((int) ($snapshot['allocation_a5_run_id'] ?? 0) !== (int) $a5->id) {
            throw new RuntimeException('Queued verification PDF is not bound to the current A5 run.');
        }

        if ((int) ($snapshot['allocation_a4_run_id'] ?? 0) !== (int) $a5->allocation_a4_run_id) {
            throw new RuntimeException('Queued verification PDF A4 lineage is outdated.');
        }

        foreach ([
            'a5_candidate_hash' => (string) $a5->candidate_result_hash,
            'a5_capacity_hash' => (string) $a5->capacity_result_hash,
        ] as $key => $currentHash) {
            $expected = (string) ($snapshot[$key] ?? '');

            if ($expected === '' || ! hash_equals($expected, $currentHash)) {
                throw new RuntimeException('Queued verification PDF source hash is outdated.');
            }
        }

        $currentDisposition = $dispositions->snapshot($a5);
        $expectedDisposition = (string) ($snapshot['disposition_hash'] ?? '');

        if ($expectedDisposition === '' || ! hash_equals($expectedDisposition, (string) $currentDisposition['hash'])) {
            throw new RuntimeException('A5.5 publication status changed before verification PDF generation.');
        }
    }

    private function update(
        ReportingExportRun $run,
        string $status,
        string $phase,
        int $percent,
        string $message,
        int $current = 0,
        int $total = 0,
    ): void {
        ReportingExportRun::query()->whereKey($run->id)->update([
            'status' => $status,
            'phase' => $phase,
            'progress_percent' => max(0, min(100, $percent)),
            'progress_current' => max(0, $current),
            'progress_total' => max(0, $total),
            'progress_message' => $message,
            'started_at' => $status === 'running' ? ($run->started_at ?: now()) : $run->started_at,
        ]);
    }
}
