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

        $summary = Registration::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN cadre_category = ? THEN 1 ELSE 0 END) as gg', [CadreCategory::General->value])
            ->selectRaw('SUM(CASE WHEN cadre_category = ? THEN 1 ELSE 0 END) as tt', [CadreCategory::Technical->value])
            ->selectRaw('SUM(CASE WHEN cadre_category = ? THEN 1 ELSE 0 END) as gt', [CadreCategory::GeneralAndTechnical->value])
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->selectRaw("SUM(CASE WHEN status = 'withheld' THEN 1 ELSE 0 END) as withheld")
            ->selectRaw("SUM(CASE WHEN validation_status = 'invalid' THEN 1 ELSE 0 END) as invalid_validation")
            ->first();

        return view('registrations.index', [
            'records' => $this->listRegistrations->execute($filters, $pagination->perPage),
            'registrationSummary' => [
                'total' => (int) ($summary?->total ?? 0),
                'gg' => (int) ($summary?->gg ?? 0),
                'tt' => (int) ($summary?->tt ?? 0),
                'gt' => (int) ($summary?->gt ?? 0),
                'active' => (int) ($summary?->active ?? 0),
                'cancelled' => (int) ($summary?->cancelled ?? 0),
                'withheld' => (int) ($summary?->withheld ?? 0),
                'invalid_validation' => (int) ($summary?->invalid_validation ?? 0),
            ],
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
