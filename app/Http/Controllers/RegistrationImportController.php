<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\RegistrationImportBatch;
use App\Services\Registrations\RegistrationImportRollbackService;
use App\Services\Registrations\RegistrationImportService;
use App\Services\Registrations\RegistrationTemplateService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Registration spreadsheet import, progress, reporting and rollback endpoints. */
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
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:102400'],
        ]);
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $batch = $service->enqueue($validated['file'], $request->user()->id, $examinationId);

        return redirect()->route('registrations.import-result', $batch)
            ->with('success', 'Import queued. Keep the queue worker running; this page will update automatically.');
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
        $rows = $batch->rows()->whereIn('action', ['rejected', 'identity_conflict'])->orderBy('source_row')->paginate(100);

        return view('registrations.import-result', ['record' => $batch, 'rows' => $rows]);
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
            'inserted_rows' => (int) $batch->inserted_rows,
            'updated_rows' => (int) $batch->updated_rows,
            'failed_rows' => (int) $batch->failed_rows,
            'warning_rows' => (int) $batch->warning_rows,
            'current_chunk' => (int) $batch->current_chunk,
            'total_chunks' => (int) $batch->total_chunks,
            'progress_percent' => (float) $batch->progress_percent,
            'failure_message' => $batch->failure_message,
            'finished' => in_array($batch->status, ['completed', 'completed_with_errors', 'failed', 'rolled_back'], true),
        ]);
    }

    public function report(RegistrationImportBatch $batch): StreamedResponse
    {
        $this->authorize('viewAny', Registration::class);

        return response()->streamDownload(function () use ($batch): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['source_row', 'reg', 'user_id', 'action', 'warnings', 'errors']);
            $batch->rows()->orderBy('id')->chunkById(1000, function ($rows) use ($stream): void {
                foreach ($rows as $row) {
                    fputcsv($stream, [
                        $row->source_row, $row->reg, $row->user_id, $row->action,
                        implode(' | ', $row->warnings ?? []), implode(' | ', $row->errors ?? []),
                    ]);
                }
            });
            fclose($stream);
        }, "registration-import-batch-{$batch->id}.csv", ['Content-Type' => 'text/csv']);
    }

    public function rollback(Request $request, RegistrationImportBatch $batch, RegistrationImportRollbackService $service): RedirectResponse
    {
        $this->authorize('import', Registration::class);
        abort_if(in_array($batch->status, ['queued', 'processing'], true), 409, 'A running import cannot be rolled back.');
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $service->rollback($batch, $request->user()->id, $validated['reason'] ?? null);

        return redirect()->route('registrations.import-result', $batch)->with('success', 'Import batch rolled back successfully.');
    }
}
