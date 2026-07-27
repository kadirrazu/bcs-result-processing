<?php

namespace App\Http\Controllers;

use App\Actions\Examinations\CreateExaminationAction;
use App\Actions\Examinations\UpdateExaminationAction;
use App\Data\ExaminationData;
use App\Enums\ExaminationStatus;
use App\Http\Requests\StoreExaminationRequest;
use App\Http\Requests\UpdateExaminationRequest;
use App\Models\Examination;
use App\Queries\Examinations\ListExaminationsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Coordinate HTTP requests for the central examination registry.
 */
final class ExaminationController extends Controller
{
    public function __construct(
        private readonly ListExaminationsQuery $listExaminations,
        private readonly CreateExaminationAction $createExamination,
        private readonly UpdateExaminationAction $updateExamination,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Examination::class);
        $search = trim((string) $request->query('search'));

        return view('examinations.index', [
            'examinations' => $this->listExaminations->execute($search),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Examination::class);

        return view('examinations.create', ['statuses' => ExaminationStatus::cases()]);
    }

    public function store(StoreExaminationRequest $request): RedirectResponse
    {
        $this->createExamination->execute(
            ExaminationData::fromValidated($request->validated(), $request->boolean('is_enabled'))
        );

        return redirect()->route('examinations.index')->with('success', 'Examination created successfully.');
    }

    public function show(Examination $examination): RedirectResponse
    {
        $this->authorize('view', $examination);

        return redirect()->route('examinations.edit', $examination);
    }

    public function edit(Examination $examination): View
    {
        $this->authorize('update', $examination);

        return view('examinations.edit', [
            'examination' => $examination,
            'statuses' => ExaminationStatus::cases(),
        ]);
    }

    public function update(UpdateExaminationRequest $request, Examination $examination): RedirectResponse
    {
        $this->updateExamination->execute(
            $examination,
            ExaminationData::fromValidated($request->validated(), $request->boolean('is_enabled'))
        );

        return redirect()->route('examinations.index')->with('success', 'Examination updated successfully.');
    }
}
