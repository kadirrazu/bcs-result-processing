<?php

namespace App\Http\Controllers;

use App\Actions\PostRelatedSubjects\CreatePostRelatedSubjectAction;
use App\Actions\PostRelatedSubjects\UpdatePostRelatedSubjectAction;
use App\Data\SubjectMasterData;
use App\Http\Requests\StorePostRelatedSubjectRequest;
use App\Http\Requests\UpdatePostRelatedSubjectRequest;
use App\Models\PostRelatedSubject;
use App\Queries\PostRelatedSubjects\ListPostRelatedSubjectsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Coordinate central post-related subject master administration. */
final class PostRelatedSubjectController extends Controller
{
    public function __construct(private readonly ListPostRelatedSubjectsQuery $list, private readonly CreatePostRelatedSubjectAction $createAction, private readonly UpdatePostRelatedSubjectAction $updateAction) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PostRelatedSubject::class);
        $search = trim((string) $request->query('search'));

        return view('master-data.subjects.index', ['records' => $this->list->execute($search), 'search' => $search, 'title' => 'Post-related Subjects', 'pretitle' => 'Master Data', 'routePrefix' => 'post-related-subjects', 'codeHelp' => 'Code used by the post-related written examination.']);
    }

    public function create(): View
    {
        $this->authorize('create', PostRelatedSubject::class);

        return view('master-data.subjects.create', ['title' => 'Post-related Subject', 'routePrefix' => 'post-related-subjects', 'codeHelp' => 'Code used by the post-related written examination.']);
    }

    public function store(StorePostRelatedSubjectRequest $request): RedirectResponse
    {
        $this->createAction->execute(SubjectMasterData::fromValidated($request->validated(), $request->boolean('is_active')));

        return redirect()->route('post-related-subjects.index')->with('success', 'Post-related subject created successfully.');
    }

    public function show(PostRelatedSubject $postRelatedSubject): RedirectResponse
    {
        $this->authorize('view', $postRelatedSubject);

        return redirect()->route('post-related-subjects.edit', $postRelatedSubject);
    }

    public function edit(PostRelatedSubject $postRelatedSubject): View
    {
        $this->authorize('update', $postRelatedSubject);

        return view('master-data.subjects.edit', ['record' => $postRelatedSubject, 'title' => 'Post-related Subject', 'routePrefix' => 'post-related-subjects', 'codeHelp' => 'Code used by the post-related written examination.']);
    }

    public function update(UpdatePostRelatedSubjectRequest $request, PostRelatedSubject $postRelatedSubject): RedirectResponse
    {
        $this->updateAction->execute($postRelatedSubject, SubjectMasterData::fromValidated($request->validated(), $request->boolean('is_active')));

        return redirect()->route('post-related-subjects.index')->with('success','Post-related subject updated successfully.');
    }
}
