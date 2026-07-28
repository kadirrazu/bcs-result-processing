<?php

namespace App\Http\Controllers;

use App\Actions\CadreMasters\CreateCadreMasterAction;
use App\Actions\CadreMasters\UpdateCadreMasterAction;
use App\Data\CadreMasterData;
use App\Enums\CadreType;
use App\Http\Requests\StoreCadreMasterRequest;
use App\Http\Requests\UpdateCadreMasterRequest;
use App\Models\CadreMaster;
use App\Queries\CadreMasters\ListCadreMastersQuery;
use App\Support\Pagination\PaginationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Coordinate central cadre master administration. */
final class CadreMasterController extends Controller
{
    public function __construct(
        private readonly ListCadreMastersQuery $list,
        private readonly CreateCadreMasterAction $createAction,
        private readonly UpdateCadreMasterAction $updateAction,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CadreMaster::class);

        $search = trim((string) $request->query('search'));
        $pagination = PaginationSettings::fromRequest($request);

        return view('master-data.cadres.index', [
            'records' => $this->list->execute($search, $pagination->perPage),
            'search' => $search,
            'perPage' => $pagination->perPage,
            'pageSizes' => PaginationSettings::ALLOWED_PER_PAGE,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CadreMaster::class);

        return view('master-data.cadres.create', ['types' => CadreType::cases()]);
    }

    public function store(StoreCadreMasterRequest $request): RedirectResponse
    {
        $this->createAction->execute(CadreMasterData::fromValidated($request->validated(), $request->boolean('is_active')));

        return redirect()->route('cadre-masters.index')->with('success', 'Cadre master created successfully.');
    }

    public function show(CadreMaster $cadreMaster): RedirectResponse
    {
        $this->authorize('view', $cadreMaster);

        return redirect()->route('cadre-masters.edit', $cadreMaster);
    }

    public function edit(CadreMaster $cadreMaster): View
    {
        $this->authorize('update', $cadreMaster);

        return view('master-data.cadres.edit', ['record' => $cadreMaster, 'types' => CadreType::cases()]);
    }

    public function update(UpdateCadreMasterRequest $request, CadreMaster $cadreMaster): RedirectResponse
    {
        $this->updateAction->execute($cadreMaster, CadreMasterData::fromValidated($request->validated(), $request->boolean('is_active')));

        return redirect()->route('cadre-masters.index')->with('success', 'Cadre master updated successfully.');
    }
}
