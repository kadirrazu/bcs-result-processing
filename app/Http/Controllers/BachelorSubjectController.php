<?php

namespace App\Http\Controllers;

use App\Actions\BachelorSubjects\CreateBachelorSubjectAction;
use App\Actions\BachelorSubjects\UpdateBachelorSubjectAction;
use App\Data\SubjectMasterData;
use App\Http\Requests\StoreBachelorSubjectRequest;
use App\Http\Requests\UpdateBachelorSubjectRequest;
use App\Models\BachelorSubject;
use App\Queries\BachelorSubjects\ListBachelorSubjectsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Coordinate central bachelor subject master administration. */
final class BachelorSubjectController extends Controller
{
    public function __construct(private readonly ListBachelorSubjectsQuery $list, private readonly CreateBachelorSubjectAction $createAction, private readonly UpdateBachelorSubjectAction $updateAction) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BachelorSubject::class);
        $search = trim((string) $request->query('search'));

        return view('master-data.subjects.index', ['records' => $this->list->execute($search), 'search' => $search, 'title' => 'Bachelor Subjects', 'pretitle' => 'Master Data', 'routePrefix' => 'bachelor-subjects', 'codeHelp' => 'Official bachelor or equivalent subject code.']);
    }

    public function create(): View
    {
        $this->authorize('create', BachelorSubject::class);

        return view('master-data.subjects.create', ['title' => 'Bachelor Subject', 'routePrefix' => 'bachelor-subjects', 'codeHelp' => 'Official bachelor or equivalent subject code.']);
    }

    public function store(StoreBachelorSubjectRequest $request): RedirectResponse
    {
        $this->createAction->execute(SubjectMasterData::fromValidated($request->validated(), $request->boolean('is_active')));

        return redirect()->route('bachelor-subjects.index')->with('success', 'Bachelor subject created successfully.');
    }

    public function show(BachelorSubject $bachelorSubject): RedirectResponse
    {
        $this->authorize('view', $bachelorSubject);

        return redirect()->route('bachelor-subjects.edit', $bachelorSubject);
    }

    public function edit(BachelorSubject $bachelorSubject): View
    {
        $this->authorize('update', $bachelorSubject);

        return view('master-data.subjects.edit', ['record' => $bachelorSubject, 'title' => 'Bachelor Subject', 'routePrefix' => 'bachelor-subjects', 'codeHelp' => 'Official bachelor or equivalent subject code.']);
    }

    public function update(UpdateBachelorSubjectRequest $request, BachelorSubject $bachelorSubject): RedirectResponse
    {
        $this->updateAction->execute($bachelorSubject, SubjectMasterData::fromValidated($request->validated(), $request->boolean('is_active')));

        return redirect()->route('bachelor-subjects.index')->with('success','Bachelor subject updated successfully.');
    }
}
