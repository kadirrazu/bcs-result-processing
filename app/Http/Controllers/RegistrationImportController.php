<?php

namespace App\Http\Controllers;

use App\Jobs\ApproveRegistrationImport;
use App\Jobs\RevalidateAndMergeCorrectedImportRows;
use App\Jobs\ValidateRegistrationImport;
use App\Models\ImportCorrectionEntry;
use App\Models\Registration;
use App\Models\RegistrationImportBatch;
use App\Services\Registrations\RegistrationImportRollbackService;
use App\Services\Registrations\RegistrationImportService;
use App\Services\Registrations\RegistrationTemplateService;
use App\Services\Imports\InvalidRowCorrectionService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class RegistrationImportController extends Controller
{
    public function create()
    {
        $this->authorize('import', Registration::class);
        $batches = RegistrationImportBatch::query()->latest('id')->paginate(20);

        return view('registrations.import', compact('batches'));
    }

    public function store(Request $request, RegistrationImportService $service, ExaminationContext $context): RedirectResponse
    {
        $this->authorize('import', Registration::class);
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:102400'],
        ]);
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $batch = $service->enqueue($validated['file'], $request->user()->id, $examinationId);

        return redirect()->route('registrations.import-result', $batch)
            ->with('success', 'File queued for fast staging. Validation will be started separately after staging finishes.');
    }

    public function template(RegistrationTemplateService $service): BinaryFileResponse
    {
        $this->authorize('viewAny', Registration::class);
        $path = storage_path('app/registration-template.xlsx');
        $service->create($path);

        return response()->download($path, 'registration-import-template.xlsx')->deleteFileAfterSend();
    }

    public function result(RegistrationImportBatch $batch)
    {
        $this->authorize('viewAny', Registration::class);
        $rows = $batch->stagingRows()
            ->whereIn('validation_status', ['invalid', 'identity_conflict', 'warning'])
            ->orderBy('source_row')
            ->paginate(100);

        return view('registrations.import-result', [
            'record' => $batch,
            'rows' => $rows,
            'corrections' => ImportCorrectionEntry::query()->where('module', 'registration')->where('batch_id', $batch->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function correctionTemplate(RegistrationImportBatch $batch, InvalidRowCorrectionService $service): BinaryFileResponse
    {
        $this->authorize('viewAny', Registration::class);
        $directory = storage_path('app/private/import-corrections');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/registration-batch-'.$batch->id.'-invalid-rows.xlsx';
        $count = $service->createCorrectionWorkbook('registration', (int) $batch->id, $path);
        abort_if($count === 0, 409, 'This batch has no invalid rows to correct.');

        return response()->download($path, 'registration-batch-'.$batch->id.'-invalid-rows.xlsx')->deleteFileAfterSend();
    }

    public function applyCorrections(Request $request, RegistrationImportBatch $batch, InvalidRowCorrectionService $service, ExaminationContext $context): RedirectResponse
    {
        $this->authorize('import', Registration::class);
        $validated = $request->validate([
            'correction_file' => ['required', 'file', 'mimes:xlsx,csv', 'max:102400'],
        ]);
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $wasApproved = (int) $batch->approved_rows > 0 || (string) $batch->status === 'approved';
        $summary = $service->apply('registration', $batch, $validated['correction_file'], $request->user());
        $batch->update([
            'status' => 'validation_queued',
            'progress_percent' => 0,
            'failure_message' => null,
            'finished_at' => null,
        ]);
        if ($wasApproved) {
            RevalidateAndMergeCorrectedImportRows::dispatch(
                $examinationId, 'registration', (int) $batch->id, $summary['source_rows'], (int) $request->user()->id
            );
        } else {
            ValidateRegistrationImport::dispatch($examinationId, $batch->id);
        }

        return back()->with('success', number_format($summary['corrected_rows']).' invalid registration row(s) were replaced from the correction file. Validation is running again now.');
    }

    public function validateBatch(RegistrationImportBatch $batch, ExaminationContext $context): RedirectResponse
    {
        $this->authorize('import', Registration::class);
        abort_unless($batch->status === 'staged', 409, 'Only a staged batch can be validated.');
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        ValidateRegistrationImport::dispatch($examinationId, $batch->id);
        $batch->update(['status' => 'validation_queued', 'progress_percent' => 0]);

        return back()->with('success', 'Registration validation queued.');
    }

    public function approve(RegistrationImportBatch $batch, ExaminationContext $context, Request $request): RedirectResponse
    {
        $this->authorize('import', Registration::class);
        abort_unless($batch->status === 'validated', 409, 'Only a validated batch can be approved.');
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        ApproveRegistrationImport::dispatch($examinationId, $batch->id, $request->user()->id);
        $batch->update(['status' => 'approval_queued', 'progress_percent' => 0]);

        return back()->with('success', 'Approved rows are queued for merge into registrations.');
    }

    public function status(RegistrationImportBatch $batch): JsonResponse
    {
        $this->authorize('viewAny', Registration::class);
        $batch->refresh();

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'total_rows' => (int) $batch->total_rows,
            'processed_rows' => (int) $batch->processed_rows,
            'staged_rows' => (int) $batch->staged_rows,
            'valid_rows' => (int) $batch->valid_rows,
            'invalid_rows' => (int) $batch->invalid_rows,
            'approved_rows' => (int) $batch->approved_rows,
            'inserted_rows' => (int) $batch->inserted_rows,
            'updated_rows' => (int) $batch->updated_rows,
            'failed_rows' => (int) $batch->failed_rows,
            'warning_rows' => (int) $batch->warning_rows,
            'identity_conflict_rows' => (int) $batch->identity_conflict_rows,
            'current_chunk' => (int) $batch->current_chunk,
            'total_chunks' => (int) $batch->total_chunks,
            'progress_percent' => (float) $batch->progress_percent,
            'failure_message' => $batch->failure_message,
            'finished' => ! in_array($batch->status, [
                'queued', 'staging', 'validation_queued', 'validating', 'approval_queued', 'approving',
            ], true),
        ]);
    }

    public function report(RegistrationImportBatch $batch): BinaryFileResponse
    {
        $this->authorize('viewAny', Registration::class);

        /*
        |--------------------------------------------------------------------------
        | Build the CSV on disk before sending the response
        |--------------------------------------------------------------------------
        |
        | A streamed response becomes invalid when any database or output error
        | occurs after HTTP headers have already been sent. For large reports that
        | produces the browser-level ERR_INVALID_RESPONSE message. Building the
        | file first keeps the response atomic: Laravel only starts the download
        | after the CSV has been created successfully.
        |
        */
        set_time_limit(0);

        $directory = storage_path('app/private/registration-import-reports');
        File::ensureDirectoryExists($directory);

        $filename = "registration-import-batch-{$batch->id}-issues.csv";
        $path = $directory.DIRECTORY_SEPARATOR.uniqid("batch-{$batch->id}-", true).'.csv';
        $file = new \SplFileObject($path, 'wb');

        // UTF-8 BOM allows Microsoft Excel to display Bangla text correctly.
        $file->fwrite("\xEF\xBB\xBF");
        $file->fputcsv([
            'source_row',
            'reg',
            'user_id',
            'validation_status',
            'warnings',
            'errors',
        ]);

        DB::connection('exam')
            ->table('registration_import_staging')
            ->select([
                'id',
                'source_row',
                'reg',
                'user_id',
                'validation_status',
                'validation_warnings',
                'validation_errors',
            ])
            ->where('batch_id', $batch->id)
            ->whereIn('validation_status', ['invalid', 'identity_conflict', 'warning'])
            ->chunkById(5000, function ($rows) use ($file): void {
                foreach ($rows as $row) {
                    $file->fputcsv([
                        $row->source_row,
                        $row->reg,
                        $row->user_id,
                        $row->validation_status,
                        $this->csvMessages($row->validation_warnings),
                        $this->csvMessages($row->validation_errors),
                    ]);
                }
            }, 'id');

        // Release the file handle before BinaryFileResponse opens the same file.
        unset($file);

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * Convert a staging JSON message column into a readable CSV cell.
     */
    private function csvMessages(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_array($value)) {
            return implode(' | ', array_map('strval', $value));
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return implode(' | ', array_map('strval', $decoded));
            }

            return $value;
        }

        return (string) $value;
    }

    public function rollback(Request $request, RegistrationImportBatch $batch, RegistrationImportRollbackService $service): RedirectResponse
    {
        $this->authorize('import', Registration::class);
        abort_if(in_array($batch->status, [
            'queued', 'staging', 'validation_queued', 'validating', 'approval_queued', 'approving',
        ], true), 409, 'A running import cannot be rolled back.');

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        // Before approval there is nothing in registrations: deleting the batch cascades staging rows.
        if (in_array($batch->status, ['staged', 'validated', 'failed'], true) && (int) $batch->approved_rows === 0) {
            $batch->update([
                'status' => 'rolled_back',
                'rolled_back_at' => now(),
                'rolled_back_by' => $request->user()->id,
                'rollback_reason' => $validated['reason'] ?? null,
            ]);

            return back()->with('success', 'Staging batch marked as rolled back. No registration data had been merged.');
        }

        $service->rollback($batch, $request->user()->id, $validated['reason'] ?? null);

        return redirect()->route('registrations.import-result', $batch)
            ->with('success', 'Approved registration batch rolled back successfully.');
    }
}
