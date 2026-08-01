<?php

namespace App\Http\Controllers;

use App\Actions\Registrations\CreateRegistrationAction;
use App\Actions\Registrations\UpdateRegistrationAction;
use App\Enums\CadreCategory;
use App\Enums\RegistrationStatus;
use App\Http\Requests\StoreRegistrationRequest;
use App\Http\Requests\UpdateRegistrationRequest;
use App\Models\District;
use App\Models\Registration;
use App\Models\RegistrationAudit;
use App\Queries\Registrations\ListRegistrationsQuery;
use App\Services\Registrations\RegistrationFormOptions;
use App\Support\Pagination\PaginationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Coordinate searchable registration CRUD for the active examination. */
final class RegistrationController extends Controller
{
    public function __construct(
        private readonly ListRegistrationsQuery $listRegistrations,
        private readonly RegistrationFormOptions $formOptions,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Registration::class);

        $pagination = PaginationSettings::fromRequest($request);
        $filters = $request->only([
            'search', 'cadre_category', 'has_quota', 'status', 'sex_code',
            'district_code', 'division_code', 'bachelor_subject_code',
        ]);

        return view('registrations.index', [
            'records' => $this->listRegistrations->execute($filters, $pagination->perPage),
            'filters' => $filters,
            'perPage' => $pagination->perPage,
            'pageSizes' => PaginationSettings::ALLOWED_PER_PAGE,
            'districts' => District::query()->where('is_active', true)->orderBy('name')->get(['code', 'name']),
            'categories' => CadreCategory::cases(),
            'statuses' => RegistrationStatus::cases(),
            ...$this->formOptions->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Registration::class);

        return view('registrations.create', $this->formOptions->get());
    }

    public function store(StoreRegistrationRequest $request, CreateRegistrationAction $action): RedirectResponse
    {
        $registration = $action->execute($request->validated());

        return redirect()->route('registrations.show', $registration)
            ->with('success', 'Registration created successfully.');
    }

    public function show(Registration $registration): View
    {
        $this->authorize('view', $registration);

        return view('registrations.show', [
            'registration' => $registration,
            'audits' => RegistrationAudit::query()
                ->where('registration_id', $registration->getKey())
                ->latest('id')
                ->limit(50)
                ->get(),
            ...$this->formOptions->get(),
        ]);
    }

    public function edit(Registration $registration): View
    {
        $this->authorize('update', $registration);

        return view('registrations.edit', [
            'registration' => $registration,
            ...$this->formOptions->get(),
        ]);
    }

    public function update(UpdateRegistrationRequest $request, Registration $registration, UpdateRegistrationAction $action): RedirectResponse
    {
        $attributes = $request->validated();
        $reason = (string) $attributes['edit_reason'];
        unset($attributes['edit_reason']);

        $action->execute($registration, $attributes, $request->user(), $reason);

        return redirect()->route('registrations.show', $registration)
            ->with('success', 'Registration updated successfully. Audit trail recorded.');
    }
}
