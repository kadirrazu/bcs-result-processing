@extends('layouts.app')

@section('title', 'Circular')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">Circular</h2>
                <div class="text-secondary">
                    Exam-specific vacancy and eligibility dataset. Cadre/Post identity is auto-resolved from Master Data.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-light">
                <div>
                    <h3 class="card-title mb-1">Quick Actions</h3>
                    <div class="text-secondary small">Common Circular administration and review actions.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-outline-primary" href="{{ route('circular.template') }}">Download Excel Template</a>
                    <a class="btn btn-outline-primary" href="{{ route('circular.view') }}">Circular View</a>
                    <a class="btn btn-outline-primary" href="{{ route('circular.entries.index') }}">Entry Listing</a>
                    <a class="btn btn-outline-success" href="{{ route('circular.authority.index') }}">Authority Preview / Finalization</a>
                    <a class="btn btn-outline-success" href="{{ route('circular.final-report.index') }}">Final Circular Report</a>
                    <a class="btn btn-outline-secondary" href="{{ route('circular.history') }}">Version &amp; Audit History</a>
                    <a class="btn btn-primary" href="{{ route('circular.entries.create') }}">Add from UI</a>
                </div>
            </div>
        </div>

        <div class="row row-cards mb-3">
            @foreach ([
                'Circular entries' => $counts['entries'],
                'Active entries' => $counts['active'],
                'Main-code rows' => $counts['main'],
                'Sub-cadre rows' => $counts['sub'],
                'Total active posts' => $counts['posts'],
            ] as $label => $value)
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm h-100">
                        <div class="card-body d-flex flex-column justify-content-between" style="min-height: 96px;">
                            <div class="text-secondary">{{ $label }}</div>
                            <div class="h2 mb-0">{{ number_format($value) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @php
            $stateValue = $state->status->value;
            $currentVersion = (int) $state->current_version;
            $approvedCurrent = $currentVersion > 0 && (int) ($state->approved_version ?? 0) === $currentVersion;
            $confirmedCurrent = $currentVersion > 0 && (int) ($state->confirmed_version ?? 0) === $currentVersion;
            $finalizedCurrent = $currentVersion > 0 && (int) ($state->finalized_version ?? 0) === $currentVersion;
            $previewReady = in_array($stateValue, ['preview_generated', 'confirmed', 'finalized'], true);
        @endphp

        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-light">
                <div>
                    <h3 class="card-title mb-1">Processing Status Board</h3>
                    <div class="text-secondary small">A quick view of where Circular processing currently stands · GMT+6 (Asia/Dhaka)</div>
                </div>
            </div>

            <div class="card-body py-2">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <span class="text-secondary me-2">Current phase</span>
                        @if ($stateValue === 'draft')
                            <span class="badge bg-yellow-lt text-yellow">{{ $state->status->label() }}</span>
                        @elseif ($stateValue === 'finalized')
                            <span class="badge bg-green-lt text-green">{{ $state->status->label() }}</span>
                        @elseif (in_array($stateValue, ['approved', 'preview_generated', 'confirmed'], true))
                            <span class="badge bg-blue-lt text-blue">{{ $state->status->label() }}</span>
                        @else
                            <span class="badge bg-secondary-lt text-secondary">{{ $state->status->label() }}</span>
                        @endif
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="text-secondary me-2">Version</span>
                        <span class="badge bg-azure-lt text-azure">Current v{{ $currentVersion }}</span>
                        <span class="badge bg-secondary-lt text-secondary">Approved v{{ $state->approved_version ?? '—' }}</span>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table mb-0">
                    <tbody>
                        <tr>
                            <td class="fw-medium">Circular Dataset</td>
                            <td>
                                @if ($currentVersion > 0 && $counts['entries'] > 0)
                                    <span class="badge bg-green-lt text-green">Ready</span>
                                @else
                                    <span class="badge bg-secondary-lt text-secondary">Not started</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    @if ($currentVersion > 0 && $counts['entries'] > 0)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('circular.view') }}">Circular View</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('circular.entries.index') }}">Entry Listing</a>
                                    @else
                                        <span class="text-secondary">Import an Excel file or add entries from UI.</span>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td class="fw-medium">Effective Dataset Approval</td>
                            <td>
                                @if ($approvedCurrent && $stateValue !== 'draft')
                                    <span class="badge bg-green-lt text-green">Effective · v{{ $state->approved_version }}</span>
                                @elseif ($stateValue === 'draft' && $currentVersion > 0)
                                    <span class="badge bg-yellow-lt text-yellow">Approval required</span>
                                @else
                                    <span class="badge bg-secondary-lt text-secondary">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if ($stateValue === 'draft' && $currentVersion > 0)
                                    <a class="btn btn-sm btn-outline-warning" href="#circular-draft-approval">Approve Current Draft</a>
                                @elseif ($approvedCurrent)
                                    <span class="text-secondary">Current dataset is approved as effective.</span>
                                @else
                                    <span class="text-secondary">Approve a validated import or complete the current dataset first.</span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <td class="fw-medium">Authority Preview</td>
                            <td>
                                @if ($previewReady)
                                    <span class="badge bg-green-lt text-green">Generated</span>
                                @elseif ($approvedCurrent)
                                    <span class="badge bg-teal-lt text-teal">Ready to generate</span>
                                @else
                                    <span class="badge bg-secondary-lt text-secondary">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if ($approvedCurrent)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('circular.authority.index') }}">Open Authority Workflow</a>
                                @else
                                    <span class="text-secondary">Approve the current Circular first.</span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <td class="fw-medium">Authority Confirmation</td>
                            <td>
                                @if ($confirmedCurrent)
                                    <span class="badge bg-green-lt text-green">Confirmed · v{{ $state->confirmed_version }}</span>
                                @elseif ($previewReady)
                                    <span class="badge bg-yellow-lt text-yellow">Awaiting confirmation</span>
                                @else
                                    <span class="badge bg-secondary-lt text-secondary">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if ($previewReady)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('circular.authority.index') }}">Review / Confirm</a>
                                @else
                                    <span class="text-secondary">Generate an Authority Preview first.</span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <td class="fw-medium">Circular Finalization</td>
                            <td>
                                @if ($finalizedCurrent)
                                    <span class="badge bg-green-lt text-green">Finalized · v{{ $state->finalized_version }}</span>
                                @elseif ($confirmedCurrent)
                                    <span class="badge bg-teal-lt text-teal">Ready for finalization</span>
                                @else
                                    <span class="badge bg-secondary-lt text-secondary">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if ($finalizedCurrent)
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a class="btn btn-sm btn-outline-success" href="{{ route('circular.authority.index') }}">Open Finalized Circular</a>
                                        <a class="btn btn-sm btn-success" href="{{ route('circular.final-report.index') }}">Final Report</a>
                                    </div>
                                @elseif ($confirmedCurrent)
                                    <a class="btn btn-sm btn-outline-success" href="{{ route('circular.authority.index') }}">Finalize Circular</a>
                                @else
                                    <span class="text-secondary">Authority confirmation is required first.</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if ($stateValue === 'draft' && $currentVersion > 0)
            <div class="card mb-3 border-warning" id="circular-draft-approval">
                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-1">Approve Corrected Circular as Effective</h3>
                        <div class="text-secondary small">A manual change created a new Draft version. Review it before making it the current effective dataset.</div>
                    </div>
                </div>
                <div class="card-body">
                    @if ($state->stale_reason)
                        <div class="alert alert-warning mb-3">
                            <strong>Why approval is required:</strong> {{ $state->stale_reason }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('circular.draft.approve') }}">
                        @csrf
                        <div class="row g-2 align-items-end">
                            <div class="col-md">
                                <label class="form-label required">Approval note</label>
                                <textarea
                                    class="form-control"
                                    name="approval_note"
                                    rows="2"
                                    required
                                    minlength="3"
                                    maxlength="2000"
                                    placeholder="Why this corrected Circular version is approved as effective"
                                ></textarea>
                            </div>
                            <div class="col-md-auto">
                                <button class="btn btn-success">Approve Current Draft as Effective</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @php
            $impact = data_get((array) $state->summary, 'downstream_impact', []);
        @endphp

        @if (!empty($impact))
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <div>
                        <h3 class="card-title mb-1">Downstream Dependency Status</h3>
                        <div class="text-secondary small">Circular corrections do not invalidate Registration, Preliminary, Written or Viva. Only Circular-dependent downstream stages are affected.</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table mb-0">
                        <thead>
                            <tr>
                                <th>Stage</th>
                                <th>Status</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($impact as $item)
                                <tr>
                                    <td class="fw-medium">{{ $item['label'] ?? '—' }}</td>
                                    <td>
                                        @if (($item['status'] ?? '') === 'stale')
                                            <span class="badge bg-yellow-lt text-yellow">STALE</span>
                                        @else
                                            <span class="badge bg-secondary-lt text-secondary">
                                                {{ strtoupper(str_replace('_', ' ', $item['status'] ?? 'not_started')) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-secondary">{{ $item['reason'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="card mb-3" id="circular-import">
            <div class="card-header">
                <div>
                    <h3 class="card-title mb-1">Circular Excel Import</h3>
                    <div class="text-secondary small">One row per Circular entry. Multiple Bachelor/PRS codes are pipe-separated with <code>|</code>.</div>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('circular.import.upload') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-md">
                            <label class="form-label required">Circular Excel</label>
                            <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                        </div>
                        <div class="col-md-auto">
                            <button class="btn btn-primary">Stage &amp; Validate</button>
                        </div>
                    </div>
                </form>

                @if ($latestBatch)
                    <hr>
                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div>
                            <div class="fw-semibold">Latest batch #{{ $latestBatch->id }}</div>
                            <div class="text-secondary small">
                                {{ $latestBatch->original_filename }} · {{ $latestBatch->valid_rows }} valid · {{ $latestBatch->invalid_rows }} invalid
                            </div>
                        </div>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('circular.import.review', $latestBatch) }}">Review</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="alert alert-info mb-0">
            <div class="fw-semibold mb-1">Circular data authority</div>
            <div>
                Names, post names and cadre type are not retyped into Circular. Enter/select the code; the system resolves the active Cadre/Sub Cadre Master and stores a snapshot for the Circular version.
            </div>
            <div class="mt-1">
                After an approved Circular is changed, a new Draft version is created. Re-approving that Draft makes the Circular effective again, while affected downstream stages remain stale until they are regenerated.
            </div>
        </div>
    </div>
</div>
@endsection
