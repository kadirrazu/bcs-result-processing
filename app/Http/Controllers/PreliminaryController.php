<?php

namespace App\Http\Controllers;

use App\Enums\PreliminaryProcessingStatus;
use App\Jobs\ApprovePreliminaryImport;
use App\Jobs\ValidatePreliminaryImport;
use App\Models\PreliminaryImportBatch;
use App\Models\PreliminaryProcessingAudit;
use App\Models\PreliminaryProcessingState;
use App\Models\PreliminaryResult;
use App\Services\Preliminary\PreliminaryAuditService;
use App\Services\Preliminary\PreliminaryImportService;
use App\Services\Preliminary\PreliminaryTemplateService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PreliminaryController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', PreliminaryResult::class);

        $state = PreliminaryProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => PreliminaryProcessingStatus::NotStarted->value],
        );

        return view('preliminary.index', [
            'state' => $state,
            'latestBatch' => PreliminaryImportBatch::query()->latest('id')->first(),
            'batches' => PreliminaryImportBatch::query()->latest('id')->paginate(15),
            'audits' => PreliminaryProcessingAudit::query()->latest('id')->limit(10)->get(),
            'counts' => [
                'results' => PreliminaryResult::query()->count(),
                'active' => PreliminaryResult::query()->where('candidate_status', 'active')->count(),
                'cancelled' => PreliminaryResult::query()->where('candidate_status', 'cancelled')->count(),
                'passed' => PreliminaryResult::query()->where('result_status', 'pass')->count(),
                'failed' => PreliminaryResult::query()->where('result_status', 'fail')->count(),
            ],
        ]);
    }

    public function store(
        Request $request,
        PreliminaryImportService $service,
        PreliminaryAuditService $audit,
        ExaminationContext $context,
    ): RedirectResponse {
        $this->authorize('process', PreliminaryResult::class);
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:102400'],
        ]);

        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $batch = $service->enqueue($validated['file'], $request->user()->id, $examinationId);
        $audit->record(
            'MARK_IMPORT_QUEUED', $request->user(), null, 'queued', null,
            ['original_name' => $batch->original_name], batchId: $batch->id,
        );

        return redirect()->route('preliminary.import.result', $batch)
            ->with('success', 'Preliminary mark file queued for fast staging.');
    }

    public function template(PreliminaryTemplateService $service): BinaryFileResponse
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $directory = storage_path('app/private/preliminary');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/preliminary-import-template.xlsx';
        $service->create($path);

        return response()->download($path, 'preliminary-import-template.xlsx')->deleteFileAfterSend();
    }

    public function result(PreliminaryImportBatch $batch)
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $rows = $batch->stagingRows()
            ->whereIn('validation_status', ['invalid', 'warning', 'identity_conflict'])
            ->orderBy('source_row')
            ->paginate(100);

        return view('preliminary.import-result', ['record' => $batch, 'rows' => $rows]);
    }

    public function validateBatch(
        PreliminaryImportBatch $batch,
        ExaminationContext $context,
        Request $request,
        PreliminaryAuditService $audit,
    ): RedirectResponse {
        $this->authorize('process', PreliminaryResult::class);
        abort_unless(
            in_array($batch->status, ['staged', 'validated', 'failed'], true) && (int) $batch->approved_rows === 0,
            409,
            'Only unapproved staged/validated data can be validated.',
        );

        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $before = $batch->status;
        $batch->update(['status' => 'validation_queued', 'progress_percent' => 0, 'failure_message' => null]);
        ValidatePreliminaryImport::dispatch($examinationId, $batch->id, $request->user()->id);
        $audit->record('MARK_VALIDATION_QUEUED', $request->user(), $before, 'validation_queued', null, batchId: $batch->id);

        return back()->with('success', 'Preliminary validation queued.');
    }

    public function approve(
        PreliminaryImportBatch $batch,
        ExaminationContext $context,
        Request $request,
        PreliminaryAuditService $audit,
    ): RedirectResponse {
        $this->authorize('process', PreliminaryResult::class);
        abort_unless(
            $batch->status === 'validated' || ($batch->status === 'failed' && (int) $batch->approved_rows === 0 && ((int) $batch->valid_rows + (int) $batch->warning_rows) > 0),
            409,
            'Only validated data can be approved.',
        );

        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $before = $batch->status;
        $batch->update(['status' => 'approval_queued', 'progress_percent' => 0, 'failure_message' => null]);
        ApprovePreliminaryImport::dispatch($examinationId, $batch->id, $request->user()->id);
        $audit->record('MARK_APPROVAL_QUEUED', $request->user(), $before, 'approval_queued', null, batchId: $batch->id);

        return back()->with('success', 'Eligible preliminary rows queued for approval and merge.');
    }

    public function status(PreliminaryImportBatch $batch): JsonResponse
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $batch->refresh();

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'total_rows' => (int) $batch->total_rows,
            'processed_rows' => (int) $batch->processed_rows,
            'staged_rows' => (int) $batch->staged_rows,
            'valid_rows' => (int) $batch->valid_rows,
            'warning_rows' => (int) $batch->warning_rows,
            'invalid_rows' => (int) $batch->invalid_rows,
            'identity_conflict_rows' => (int) $batch->identity_conflict_rows,
            'approved_rows' => (int) $batch->approved_rows,
            'inserted_rows' => (int) $batch->inserted_rows,
            'updated_rows' => (int) $batch->updated_rows,
            'progress_percent' => (float) $batch->progress_percent,
            'failure_message' => $batch->failure_message,
            'finished' => ! in_array($batch->status, [
                'queued', 'staging', 'validation_queued', 'validating', 'approval_queued', 'approving',
            ], true),
        ]);
    }

    public function report(PreliminaryImportBatch $batch): BinaryFileResponse
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        set_time_limit(0);

        $directory = storage_path('app/private/preliminary-import-reports');
        File::ensureDirectoryExists($directory);
        $filename = "preliminary-import-batch-{$batch->id}-issues.csv";
        $path = $directory.DIRECTORY_SEPARATOR.uniqid("batch-{$batch->id}-", true).'.csv';
        $file = new \SplFileObject($path, 'wb');
        $file->fwrite("\xEF\xBB\xBF");
        $file->fputcsv(['source_row', 'reg', 'user', 'raw_mark', 'candidate_status', 'validation_status', 'warnings', 'errors']);

        DB::connection('exam')->table('preliminary_import_staging')
            ->select(['id', 'source_row', 'reg', 'user_id', 'raw_mark', 'raw_candidate_status', 'validation_status', 'validation_warnings', 'validation_errors'])
            ->where('batch_id', $batch->id)
            ->whereIn('validation_status', ['invalid', 'warning', 'identity_conflict'])
            ->chunkById(5000, function ($rows) use ($file): void {
                foreach ($rows as $row) {
                    $file->fputcsv([
                        $row->source_row,
                        $row->reg,
                        $row->user_id,
                        $row->raw_mark,
                        $row->raw_candidate_status,
                        $row->validation_status,
                        $this->csvMessages($row->validation_warnings),
                        $this->csvMessages($row->validation_errors),
                    ]);
                }
            }, 'id');

        unset($file);

        return response()->download($path, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ])->deleteFileAfterSend(true);
    }

    private function csvMessages(mixed $value): string
    {
        if ($value === null || $value === '') { return ''; }
        if (is_array($value)) { return implode(' | ', array_map('strval', $value)); }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? implode(' | ', array_map('strval', $decoded)) : (string) $value;
    }
}
