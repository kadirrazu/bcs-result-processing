<?php

namespace App\Http\Controllers;

use App\Enums\WrittenProcessingStatus;
use App\Models\WrittenImportBatch;
use App\Models\WrittenProcessingAudit;
use App\Models\WrittenProcessingState;
use App\Models\WrittenResult;
use App\Services\Written\WrittenSubjectConfig;
use App\Services\Written\WrittenTemplateService;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class WrittenController extends Controller
{
    public function index(WrittenSubjectConfig $subjects): View
    {
        $this->authorize('viewAny', WrittenResult::class);

        $state = WrittenProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => WrittenProcessingStatus::NotStarted->value],
        );

        return view('written.index', [
            'state' => $state,
            'latestBatch' => WrittenImportBatch::query()->latest('id')->first(),
            'audits' => WrittenProcessingAudit::query()->latest('id')->limit(10)->get(),
            'counts' => [
                'results' => WrittenResult::query()->count(),
                'warnings' => WrittenResult::query()->where('validation_status', 'warning')->count(),
                'active' => WrittenResult::query()->where('status', 'active')->count(),
                'cancelled' => WrittenResult::query()->where('status', 'cancelled')->count(),
                'withheld' => WrittenResult::query()->where('status', 'withheld')->count(),
            ],
            'ruleSummary' => [
                'general_full_mark' => $subjects->trackFullMark('general'),
                'technical_full_mark' => $subjects->trackFullMark('technical'),
                'general_pass_mark' => $subjects->trackPassThreshold('general'),
                'technical_pass_mark' => $subjects->trackPassThreshold('technical'),
                'paper_crash_percent' => (float) config('written.paper_crash_percent'),
                'high_mark_review_percent' => (float) config('written.high_mark_review_percent'),
            ],
        ]);
    }

    public function template(WrittenTemplateService $service): BinaryFileResponse
    {
        $this->authorize('viewAny', WrittenResult::class);

        $directory = storage_path('app/private/written');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/written-import-template.xlsx';
        $service->create($path);

        return response()->download($path, 'written-import-template.xlsx')->deleteFileAfterSend();
    }
}
