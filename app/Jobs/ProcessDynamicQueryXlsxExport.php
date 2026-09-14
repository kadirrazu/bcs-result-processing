<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Models\ReportingExportRun;
use App\Services\Reporting\DynamicQuery\DynamicQueryCompiler;
use App\Services\Reporting\DynamicQuery\DynamicQueryXlsxWriter;
use App\Services\Reporting\DynamicQuery\SavedDynamicReportService;
use App\Services\Reporting\ReportExportFileStore;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

final class ProcessDynamicQueryXlsxExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(public readonly int $examinationId, public readonly int $exportRunId)
    {
        $this->onQueue((string) config('allocation.queue', 'imports'));
    }

    public function handle(
        ExaminationConnectionManager $connections,
        DynamicQueryCompiler $compiler,
        DynamicQueryXlsxWriter $writer,
        ReportExportFileStore $files,
        SavedDynamicReportService $savedReports,
    ): void {
        $exam = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($exam);
        $startedAt = hrtime(true);
        $definition = [];
        $savedReportId = null;
        $total = 0;

        try {
            $run = ReportingExportRun::query()->where('module', 'dynamic_query')->where('export_type', 'XLSX')->findOrFail($this->exportRunId);
            $parameters = (array) ($run->parameters ?? []);
            $definition = (array) ($parameters['definition'] ?? []);
            $savedReportId = isset($parameters['saved_report_id']) ? (int) $parameters['saved_report_id'] : null;
            if ($definition === []) throw new RuntimeException('Dynamic report definition is missing.');
            ReportingExportRun::query()->whereKey($run->id)->update(['status'=>'running','phase'=>'PREPARING','progress_percent'=>5,'progress_message'=>'Preparing semantic query and current authoritative sources.','started_at'=>now()]);

            $dataset = $compiler->exportDataset($definition);
            $expected = (array) $run->source_snapshot;
            foreach (['preliminary_finalization_run_id','written_processing_run_id','viva_processing_run_id','tabulation_run_id','merit_run_id','allocation_a5_run_id','allocation_disposition_revision','allocation_disposition_hash','choice_validation_finalization_run_id','choice_validation_version','choice_optimization_hash','final_allocation_ready_choice_hash','circular_version'] as $key) {
                $queued = (string) ($expected[$key] ?? ''); $current = (string) ($dataset['authority'][$key] ?? '');
                if ($queued !== $current) throw new RuntimeException('Queued report source changed before generation. Regenerate the report.');
            }
            $path = $files->outputPath('dynamic-query', (int) $run->id, 'xlsx');
            $total = (int) $dataset['count'];
            $progress = function (int $current, int $ignored) use ($run, $total): void {
                $percent = $total > 0 ? 15 + (int) floor(min(1, $current / $total) * 75) : 90;
                ReportingExportRun::query()->whereKey($run->id)->update(['phase'=>'GENERATING','progress_percent'=>min(90,$percent),'progress_current'=>$current,'progress_total'=>$total,'progress_message'=>'Writing report rows to XLSX.']);
            };
            $writer->write(
                $path,
                $definition,
                $dataset['columns'],
                $dataset['rows'],
                $progress,
                $total,
                (array) ($dataset['totals'] ?? []),
                isset($dataset['total_label']) ? (string) $dataset['total_label'] : null,
            );
            $name = 'dynamic-query-report-'.now()->format('Ymd-His').'.xlsx';
            ReportingExportRun::query()->whereKey($run->id)->update(['status'=>'completed','phase'=>'COMPLETED','progress_percent'=>100,'progress_current'=>$total,'progress_total'=>$total,'progress_message'=>'XLSX report completed and is ready to download.','file_path'=>$path,'file_name'=>$name,'file_mime'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','file_hash'=>hash_file('sha256',$path) ?: null,'completed_at'=>now(),'failure_message'=>null]);

            if ($savedReportId) {
                $savedReports->recordExecution(
                    $savedReportId,
                    $definition,
                    'xlsx_export',
                    $total,
                    0,
                    (int) round((hrtime(true) - $startedAt) / 1_000_000),
                    'completed',
                    null,
                    $run->generated_by ? (int) $run->generated_by : null,
                );
            }
        } catch (Throwable $e) {
            ReportingExportRun::query()->whereKey($this->exportRunId)->update(['status'=>'failed','phase'=>'FAILED','progress_message'=>'Dynamic Query XLSX generation failed.','failure_message'=>mb_substr($e->getMessage(),0,65000),'completed_at'=>now()]);
            if ($savedReportId && $definition !== []) {
                $savedReports->recordExecution(
                    $savedReportId,
                    $definition,
                    'xlsx_export',
                    $total,
                    0,
                    (int) round((hrtime(true) - $startedAt) / 1_000_000),
                    'failed',
                    $e->getMessage(),
                    isset($run) && $run->generated_by ? (int) $run->generated_by : null,
                );
            }
            throw $e;
        } finally {
            $connections->disconnect();
        }
    }
}
