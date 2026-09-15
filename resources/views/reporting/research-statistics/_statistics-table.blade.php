@php
    $rankedTop10 = (bool) ($rankedTop10 ?? false);
    $embedded = (bool) ($embedded ?? false);
    $rows = $data['rows'] ?? [];
    $total = $data['total'] ?? null;
    $showSerial = count($rows) > 1;
    $numericColumns = 1 + count($genderNames);
@endphp
@if(!$embedded)<div class="card mb-3"><div class="card-header"><h3 class="card-title">{{ $title }}</h3></div>@endif
<div class="table-responsive">
<table class="table table-vcenter table-striped mb-0">
    <thead>
        <tr class="fw-bold">
            @if($showSerial)<th class="text-center align-middle" style="width:70px">Serial</th>@endif
            <th class="fw-bold align-middle">{{ $rankedTop10 ? 'Institution' : 'Category' }}</th>
            <th class="text-center align-middle fw-bold">Total</th>
            @foreach($genderNames as $gender)<th class="text-center align-middle fw-bold">{{ $gender }}</th>@endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $i=>$row)
            <tr>
                @if($showSerial)<td class="text-center align-middle">{{ $i+1 }}</td>@endif
                <td class="align-middle">{{ $row['label'] }}</td>
                <td class="text-center align-middle fw-semibold">{{ number_format($row['total']) }}</td>
                @foreach($genderNames as $gender)<td class="text-center align-middle">{{ number_format($row['genders'][$gender] ?? 0) }}</td>@endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($genderNames)+2+($showSerial?1:0) }}" class="text-center text-secondary py-4">No data available.</td></tr>
        @endforelse
    </tbody>
    @if($total && count($rows)>1)
        <tfoot>
            <tr class="fw-bold bg-light">
                @if($showSerial)<td class="text-center align-middle fw-bold"></td>@endif
                <td class="align-middle fw-bold">{{ $rankedTop10 ? 'Total — Top 10 Institutions' : 'Total' }}</td>
                <td class="text-center align-middle fw-bold">{{ number_format($total['total']) }}</td>
                @foreach($genderNames as $gender)<td class="text-center align-middle fw-bold">{{ number_format($total['genders'][$gender] ?? 0) }}</td>@endforeach
            </tr>
        </tfoot>
    @endif
</table>
</div>
@if(!$embedded)</div>@endif
