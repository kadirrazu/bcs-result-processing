@extends('layouts.app')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">Choice Optimization</h2>
                <div class="text-secondary">Optional transformation layer between finalized Choice Validation and Allocation.</div>
            </div>
        </div>
    </div>
</div>
<div class="page-body">
    <div class="container-xl">
        <div class="row row-cards mb-3">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Optimization Setting</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="badge {{ $setting->optimization_enabled ? 'bg-green-lt' : 'bg-secondary-lt' }}">
                                {{ $setting->optimization_enabled ? 'YES — ENABLED' : 'NO — BYPASS' }}
                            </span>
                        </div>
                        <p class="text-secondary">
                            YES: Viva OMR override and previous-BCS optimization must be completed before Allocation.<br>
                            NO: Allocation consumes finalized Validated Choice directly; this module performs no transformation.
                        </p>
                        <form method="POST" action="{{ route('choice-optimization.setting.update') }}" class="d-flex gap-2">
                            @csrf
                            <button class="btn {{ $setting->optimization_enabled ? 'btn-primary' : 'btn-outline-primary' }}" name="optimization_enabled" value="1" type="submit">YES</button>
                            <button class="btn {{ ! $setting->optimization_enabled ? 'btn-secondary' : 'btn-outline-secondary' }}" name="optimization_enabled" value="0" type="submit">NO</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Processing Status Board</h3></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="text-secondary small">Current state</div><div class="fw-semibold">{{ strtoupper(str_replace('_', ' ', $state->status)) }}</div></div></div>
                            <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="text-secondary small">Allocation choice source</div><div class="fw-semibold">{{ $setting->optimization_enabled ? 'Finalized Optimized Choice' : 'Finalized Validated Choice' }}</div></div></div>
                            <div class="col-12"><div class="alert alert-info mb-0">Viva OMR establishes the effective choice. INCLUDED Previous BCS Repository recommendations and only the latest approved Google Form batch are consolidated first, then Choice Optimization runs once.</div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($setting->optimization_enabled)
        <div class="card mb-3">
            <div class="card-header"><div><h3 class="card-title">Google Form Historical Recommendation</h3><div class="card-subtitle">Optional YES/NO step. reg = current BCS registration; bcs + cadre = previous BCS recommendation.</div></div></div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <div class="text-secondary small mb-2">Decision</div>
                        @if($setting->google_form_enabled === null)
                            <span class="badge bg-yellow-lt mb-3">NOT DECIDED</span>
                        @elseif($setting->google_form_enabled)
                            <span class="badge bg-green-lt mb-3">YES — ENABLED</span>
                        @else
                            <span class="badge bg-secondary-lt mb-3">NO — BYPASSED / COMPLETE</span>
                        @endif
                        <form method="POST" action="{{ route('choice-optimization.google-form.decision') }}" class="d-flex gap-2">@csrf
                            <button class="btn {{ $setting->google_form_enabled === true ? 'btn-primary' : 'btn-outline-primary' }}" name="google_form_enabled" value="1">YES</button>
                            <button class="btn {{ $setting->google_form_enabled === false ? 'btn-secondary' : 'btn-outline-secondary' }}" name="google_form_enabled" value="0">NO</button>
                        </form>
                    </div>
                    <div class="col-lg-5">
                        @if($setting->google_form_enabled === true)
                            <div class="text-secondary small mb-2">Upload manually verified file — columns: <code>reg</code>, <code>bcs</code>, <code>cadre</code></div>
                            <form method="POST" action="{{ route('choice-optimization.google-form.upload') }}" enctype="multipart/form-data">@csrf
                                <div class="input-group"><input class="form-control" type="file" name="file" accept=".xlsx,.csv" required><button class="btn btn-primary">Upload & Stage</button></div>
                            </form>
                        @else
                            <div class="text-secondary">{{ $setting->google_form_enabled === false ? 'No Google Form data is required. This step does not gate downstream processing.' : 'Choose YES or NO first.' }}</div>
                        @endif
                    </div>
                    <div class="col-lg-3">
                        <div class="text-secondary small">Latest Batch Accepted</div><div class="h3 mb-1">{{ number_format($googleFormAcceptedCount) }}</div>
                        @if($latestGoogleFormBatch)
                            <div class="small text-secondary mb-2">Rows {{ number_format($latestGoogleFormBatch->total_rows) }} · Valid {{ number_format($latestGoogleFormBatch->valid_rows) }}</div>
                        @endif
                        @if($latestGoogleFormBatch)<a class="btn btn-sm btn-outline-primary" href="{{ route('choice-optimization.google-form.show',$latestGoogleFormBatch) }}">Latest Batch #{{ $latestGoogleFormBatch->id }}</a>@endif
                    </div>
                </div>
                @if($setting->google_form_enabled === true)<div class="alert alert-info mt-3 mb-0">Only the latest approved Google Form batch participates in optimization; older batches are history only. If corrections are required, upload a complete replacement batch so the new latest batch remains the full authoritative snapshot.</div>@endif
            </div>
        </div>

        <div class="row row-cards">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Viva OMR Choice Source</h3></div>
                    <div class="card-body">
                        <div class="mb-3 text-secondary">Columns: <code>reg</code>, <code>change_choice</code>, <code>opt_01 ... opt_{{ str_pad((string) config('choice-optimization.omr_max_choices', 20), 2, '0', STR_PAD_LEFT) }}</code>.</div>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <a class="btn btn-outline-secondary" href="{{ route('choice-optimization.omr.template') }}">Download Template</a>
                        </div>
                        <form method="POST" action="{{ route('choice-optimization.omr.upload') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="input-group">
                                <input class="form-control" type="file" name="file" accept=".xlsx,.csv" required>
                                <button class="btn btn-primary" type="submit">Upload & Stage</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Latest OMR Batch</h3></div>
                    <div class="card-body">
                        @if($latestOmrBatch)
                            <div class="row g-3">
                                <div class="col-md-4"><div class="text-secondary small">Status</div><div class="fw-semibold">{{ strtoupper(str_replace('_',' ', $latestOmrBatch->status)) }}</div></div>
                                <div class="col-md-4"><div class="text-secondary small">Rows</div><div class="fw-semibold">{{ number_format($latestOmrBatch->total_rows) }}</div></div>
                                <div class="col-md-4"><div class="text-secondary small">Conflict</div><div class="fw-semibold">{{ number_format($latestOmrBatch->conflict_rows) }}</div></div>
                            </div>
                            <div class="mt-3"><a class="btn btn-outline-primary" href="{{ route('choice-optimization.omr.show', $latestOmrBatch) }}">Review Batch</a></div>
                        @else
                            <div class="text-secondary">No Viva OMR source has been uploaded yet.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <form id="historical-usage-form" method="POST" action="{{ route('choice-optimization.historical.source-usage') }}">@csrf</form>
            <form method="POST" action="{{ route('choice-optimization.historical.pull') }}">
                @csrf
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Historical Previous BCS Sources</h3>
                        <div class="card-subtitle">
                            Pull/Re-pull repository snapshots independently. The same row selection drives Pull, Include/Exclude, and Use Only Selected. Excluded source data remains preserved for audit/history.
                            <div class="small mt-1"><strong>Use Only Selected</strong> makes the selected pulled sources the complete optimization source set and excludes every other pulled Historical source.</div>
                        </div>
                    </div>
                    <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                        <button class="btn btn-outline-secondary" id="historical-deselect-all" type="button" disabled>Deselect All</button>
                        <button class="btn btn-outline-success" id="historical-include-selected" type="submit" name="action" value="include" form="historical-usage-form" disabled>Include Selected</button>
                        <button class="btn btn-outline-secondary" id="historical-exclude-selected" type="submit" name="action" value="exclude" form="historical-usage-form" disabled>Exclude Selected</button>
                        <button class="btn btn-outline-primary" id="historical-use-only-selected" type="submit" name="action" value="only" form="historical-usage-form" disabled>Use Only Selected</button>
                        <button class="btn btn-primary" id="historical-pull-selected" type="submit" disabled>
                            Pull / Re-pull Selected
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th style="width:40px">
                                <input class="form-check-input" type="checkbox" id="historical-select-all" aria-label="Select all Historical BCS sources">
                            </th>
                            <th>Previous BCS</th>
                            <th>Effective Repository</th>
                            <th>Workspace Snapshot</th>
                            <th>Match Summary</th>
                            <th>Status</th>
                            <th class="text-end">View</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($historicalRepositories as $repository)
                            @php
                                $effective = $repository->currentEffectiveDataset;
                                $source = $historicalSourceMap->get((int) $repository->bcs_number);
                                $updateAvailable = $source && (int) $source->repository_dataset_id !== (int) $effective->id;
                                $running = $source && in_array($source->status, ['pull_queued','pulling'], true);
                                $statusBadge = match($source?->status) {
                                    'pulled' => 'bg-green-lt',
                                    'pull_queued', 'pulling' => 'bg-blue-lt',
                                    'failed' => 'bg-red-lt',
                                    default => 'bg-secondary-lt',
                                };
                            @endphp
                            <tr class="historical-source-row"
                                data-source-running="{{ $running ? '1' : '0' }}"
                                @if($source) data-status-url="{{ route('choice-optimization.historical.status', $source) }}" @endif>
                                <td>
                                    <input
                                        class="form-check-input historical-source-checkbox"
                                        type="checkbox"
                                        name="bcs_numbers[]"
                                        value="{{ $repository->bcs_number }}"
                                        data-source-id="{{ $source?->id }}"
                                        @disabled($running)
                                        aria-label="Select BCS {{ $repository->bcs_number }}"
                                    >
                                </td>
                                <td>
                                    <strong>{{ $repository->bcs_number }}</strong>
                                    @if($source)
                                        <div class="mt-1">
                                            @if($source->included_in_optimization)
                                                <span class="badge bg-green-lt">INCLUDED</span>
                                            @else
                                                <span class="badge bg-secondary-lt">EXCLUDED</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-green-lt">v{{ $effective->version }} EFFECTIVE</span>
                                    <div class="small text-secondary mt-1"><code>{{ substr((string) $effective->dataset_hash, 0, 12) }}…</code></div>
                                </td>
                                <td>
                                    @if($source)
                                        v{{ $source->repository_dataset_version }}
                                        @if($updateAvailable)
                                            <span class="badge bg-yellow-lt ms-1">UPDATE AVAILABLE</span>
                                        @endif
                                        @if($source->last_pulled_at)
                                            <div class="small text-secondary">{{ $source->last_pulled_at->format('d M Y, h:i A') }}</div>
                                        @endif
                                    @else
                                        <span class="text-secondary">Not pulled</span>
                                    @endif
                                </td>
                                <td data-role="historical-match-summary">
                                    @if($source && $source->status === 'pulled')
                                        <span class="text-success">{{ number_format($source->matched_count) }} matched</span>
                                        · <span class="text-warning">{{ number_format($source->review_count) }} review</span>
                                        · <span class="text-secondary">{{ number_format($source->no_match_count) }} no match</span>
                                    @elseif($running)
                                        <div class="progress progress-sm mb-1">
                                            <div class="progress-bar progress-bar-indeterminate"></div>
                                        </div>
                                        <span class="small text-secondary">Historical matching in progress…</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-role="historical-status">
                                    @if($source)
                                        <span class="badge {{ $statusBadge }}">{{ strtoupper(str_replace('_',' ', $source->status)) }}</span>
                                        @if($running)
                                            <div class="progress progress-sm mt-2" style="min-width:120px">
                                                <div class="progress-bar progress-bar-indeterminate"></div>
                                            </div>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary-lt">NOT PULLED</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($source)
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('choice-optimization.historical.show', $source) }}">View</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-5">
                                    No EFFECTIVE Previous BCS repository dataset is available in the central repository.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Consolidated Historical Choice Optimization</h3>
                    <div class="card-subtitle">
                        INCLUDED Previous BCS recommendations + the latest approved Google Form batch are consolidated, then the current effective choice is trimmed once into the Allocation-ready sequence.
                    </div>
                </div>
                <div class="ms-auto d-flex gap-2">
                    @if($historicalOptimizationRows > 0)
                        <a class="btn btn-outline-primary" href="{{ route('choice-optimization.historical-choices.index') }}">View Results</a>
                    @endif
                    <form method="POST" action="{{ route('choice-optimization.historical-choices.process') }}" class="mb-0">
                        @csrf
                        <button
                            class="btn btn-primary"
                            type="submit"
                            @disabled($setting->google_form_enabled === null || $historicalPendingReviewCount > 0 || in_array((string)$state->status, ['historical_optimization_queued','historical_optimizing'], true))
                        >
                            {{ $historicalOptimizationRows > 0 ? 'Re-process Optimization' : 'Process Optimization' }}
                        </button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="text-secondary small">Pending Historical Review</div>
                        <div class="h3 mb-0 {{ $historicalPendingReviewCount > 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($historicalPendingReviewCount) }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-secondary small">Optimization Status</div>
                        <div class="fw-semibold">{{ strtoupper(str_replace('_',' ',(string)$state->status)) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-secondary small">Current Output Rows</div>
                        <div class="h3 mb-0">{{ number_format($historicalOptimizationRows) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-secondary small">Authority</div>
                        @if((string)$state->status === 'finalized' && !$state->is_stale)
                            <span class="badge bg-green-lt">FINALIZED / ALLOCATION READY</span>
                        @elseif($state->is_stale)
                            <span class="badge bg-yellow-lt">STALE / RE-PROCESS REQUIRED</span>
                        @else
                            <span class="badge bg-secondary-lt">NOT FINALIZED</span>
                        @endif
                    </div>
                </div>

                <div class="alert alert-info mt-3 mb-0">
                    <strong>Consolidated snapshot:</strong> {{ number_format($consolidatedHistoricalCount) }} candidate + previous BCS key(s).
                    Multiple cadre values from different sources are retained and evaluated independently; any matching cadre may define the cutoff.
                    @if($setting->google_form_enabled === false) Google Form = NO, so only confirmed Previous BCS Repository recommendations participate. @endif
                </div>

                @if($setting->google_form_enabled === null)
                    <div class="alert alert-warning mt-3 mb-0">Decide Google Form YES or NO before starting Consolidated Historical Choice Optimization.</div>
                @elseif($historicalPendingReviewCount > 0)
                    <div class="alert alert-warning mt-3 mb-0">
                        Resolve all Historical Match REVIEW items before starting Consolidated Historical Choice Optimization.
                    </div>
                @elseif($state->is_stale && $state->stale_reason)
                    <div class="alert alert-warning mt-3 mb-0">
                        <strong>Re-processing required:</strong> {{ $state->stale_reason }}
                    </div>
                @endif
            </div>
        </div>

        <script>
        (() => {
            const all = document.getElementById('historical-select-all');
            const deselectAll = document.getElementById('historical-deselect-all');
            const submit = document.getElementById('historical-pull-selected');
            const boxes = [...document.querySelectorAll('.historical-source-checkbox:not(:disabled)')];
            const includeSelected = document.getElementById('historical-include-selected');
            const excludeSelected = document.getElementById('historical-exclude-selected');
            const useOnlySelected = document.getElementById('historical-use-only-selected');
            const usageForm = document.getElementById('historical-usage-form');
            const sourceRows = [...document.querySelectorAll('.historical-source-row[data-status-url]')];
            const fmt = new Intl.NumberFormat();

            const selectedBoxes = () => boxes.filter(box => box.checked);
            const selectedPulledBoxes = () => selectedBoxes().filter(box => box.dataset.sourceId);

            const sync = () => {
                const selected = selectedBoxes().length;
                const usageSelected = selectedPulledBoxes().length;

                if(submit) submit.disabled = selected === 0;
                if(includeSelected) includeSelected.disabled = usageSelected === 0;
                if(excludeSelected) excludeSelected.disabled = usageSelected === 0;
                if(useOnlySelected) useOnlySelected.disabled = usageSelected === 0;
                if(deselectAll) deselectAll.disabled = selected === 0;

                if(all) {
                    all.checked = boxes.length > 0 && selected === boxes.length;
                    all.indeterminate = selected > 0 && selected < boxes.length;
                }
            };

            // Include/Exclude operate on pulled Historical sources only. We materialize the
            // selected source IDs into the separate usage form immediately before submit so
            // the same visible row checkbox can safely drive both Pull and Usage workflows.
            usageForm?.addEventListener('submit', () => {
                usageForm.querySelectorAll('input[data-generated-source-id="1"]').forEach(el => el.remove());
                selectedPulledBoxes().forEach(box => {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'source_ids[]';
                    hidden.value = box.dataset.sourceId;
                    hidden.dataset.generatedSourceId = '1';
                    usageForm.appendChild(hidden);
                });
            });

            all?.addEventListener('change', () => {
                boxes.forEach(box => box.checked = all.checked);
                sync();
            });

            deselectAll?.addEventListener('click', () => {
                boxes.forEach(box => box.checked = false);
                sync();
            });

            boxes.forEach(box => box.addEventListener('change', sync));
            sync();

            const runningStates = ['pull_queued', 'pulling'];
            let observedRunning = sourceRows.some(row => row.dataset.sourceRunning === '1');

            const badgeClass = (status) => {
                if(status === 'pulled') return 'badge bg-green-lt';
                if(runningStates.includes(status)) return 'badge bg-blue-lt';
                if(status === 'failed') return 'badge bg-red-lt';
                return 'badge bg-secondary-lt';
            };

            const renderRow = (row, data) => {
                const statusCell = row.querySelector('[data-role="historical-status"]');
                const summaryCell = row.querySelector('[data-role="historical-match-summary"]');
                const status = String(data.status || '');
                const running = !!data.running;

                row.dataset.sourceRunning = running ? '1' : '0';

                if(statusCell) {
                    statusCell.innerHTML = `
                        <span class="${badgeClass(status)}">${status.replaceAll('_',' ').toUpperCase()}</span>
                        ${running ? `
                            <div class="progress progress-sm mt-2" style="min-width:120px">
                                <div class="progress-bar progress-bar-indeterminate"></div>
                            </div>
                        ` : ''}
                    `;
                }

                if(summaryCell) {
                    if(running) {
                        summaryCell.innerHTML = `
                            <div class="progress progress-sm mb-1">
                                <div class="progress-bar progress-bar-indeterminate"></div>
                            </div>
                            <span class="small text-secondary">Historical matching in progress…</span>
                        `;
                    } else if(status === 'pulled') {
                        summaryCell.innerHTML = `
                            <span class="text-success">${fmt.format(data.matched_count || 0)} matched</span>
                            · <span class="text-warning">${fmt.format(data.review_count || 0)} review</span>
                            · <span class="text-secondary">${fmt.format(data.no_match_count || 0)} no match</span>
                        `;
                    } else if(status === 'failed') {
                        summaryCell.innerHTML = `<span class="text-danger small">${data.failure_message || 'Historical pull failed.'}</span>`;
                    }
                }
            };

            const pollHistoricalSources = async () => {
                if(sourceRows.length === 0) return;

                let anyRunning = false;

                await Promise.all(sourceRows.map(async (row) => {
                    const url = row.dataset.statusUrl;
                    if(!url) return;

                    try {
                        const response = await fetch(url, {
                            headers: {'Accept':'application/json'},
                            cache: 'no-store',
                        });
                        if(!response.ok) return;

                        const data = await response.json();
                        renderRow(row, data);

                        if(data.running) {
                            anyRunning = true;
                            observedRunning = true;
                        }
                    } catch (_) {}
                }));

                if(observedRunning && !anyRunning) {
                    window.setTimeout(() => window.location.replace(window.location.href), 300);
                }
            };

            if(sourceRows.some(row => row.dataset.sourceRunning === '1')) {
                pollHistoricalSources();
                window.setInterval(pollHistoricalSources, 1500);
            }
        })();
        </script>
        @endif
    </div>
</div>
@endsection
