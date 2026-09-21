@extends('layouts.app')
@section('title',$post->post_code.' Non-Cadre Report')
@section('page-header')
<div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">NC5 · {{ ucfirst($mode) }}</div><h2 class="page-title d-flex flex-wrap align-items-center gap-2">
    <span>{{ $post->post_code }} — {{ $post->post_title }}</span>
    <span class="fs-5 fw-normal">- Organization: <span class="fw-semibold">{{ $post->entity ?: '—' }}</span> - Ministry: <span class="fw-semibold">{{ $post->ministry ?: '—' }}</span></span>
    <span class="fs-5 fw-normal">[Total Post: <span class="text-primary fw-bold">{{ $post->post_count }}</span>, Allocated: <span class="text-success fw-bold">{{ $post->allocated_post ?? 0 }}</span>]</span>
</h2><div class="text-secondary">{{ $allocatedOnly ? 'Allocated Candidates' : 'Post Choice & Quota Eligibility' }} {{ $mode==='booklet'?'Booklet Publishing':'Verification' }} Report</div></div><div class="col-auto d-flex gap-2 no-print"><form method="POST" action="{{ $allocatedOnly ? route('non-cadre.reporting.post.allocated.pdf',[$mode,$post->post_code]) : route('non-cadre.reporting.post.pdf',[$mode,$post->post_code]) }}">@csrf<button class="btn btn-primary">Export PDF</button></form><button class="btn btn-outline-primary" type="button" onclick="window.print()">Print</button><a class="btn btn-outline-secondary" href="{{ route('non-cadre.reporting.posts',$mode) }}">Back</a></div></div>
@endsection
@section('content')
<style>.table th,.table td{border:1px solid var(--tblr-border-color)!important}@media print{.navbar,.page-header .btn,.footer,.no-print,.card-footer{display:none!important}.container-xl{max-width:none!important;padding:0!important}.card{box-shadow:none!important}}</style>
@php($serial = ($rows->currentPage()-1)*$rows->perPage())
<div class="card"><div class="table-responsive"><table class="table table-bordered table-vcenter table-sm">
<thead><tr class="text-center align-middle"><th>SL</th><th>Common Merit</th>@if($mode==='booklet')<th class="text-start">Candidate Information</th>@endif<th>Allocation Ready Choice</th><th>Choice Position</th><th>Candidate Quota</th><th>Final Allocation</th><th>Basis</th><th class="text-start">Remarks</th></tr></thead>
<tbody>@foreach($rows as $r)<tr>
<td class="text-center align-middle">{{ ++$serial }}</td><td class="text-center align-middle fw-bold">{{ $r->common_merit_position }}</td>
@if($mode==='booklet')<td class="align-middle"><strong>Reg:</strong> {{ $r->reg }}<br><strong>Name:</strong> {{ $r->name }}<br><strong>DOB:</strong> {{ $r->birth_date }}</td>@endif
@php($choices = json_decode((string)$r->allocation_ready_choices,true) ?: [])
<td class="align-middle small" style="min-width:320px"><div style="display:grid;grid-template-columns:repeat(10,max-content);gap:3px 4px;align-items:center">@forelse($choices as $i=>$choice)@php($isAllocated=(string)$choice===(string)$r->allocated_post_code)<span class="badge {{ $isAllocated ? 'bg-red-lt text-red fw-bold' : 'bg-green-lt' }} d-inline-flex flex-column align-items-center justify-content-center px-2 py-1" style="line-height:1.05;min-width:44px"><span style="font-size:9px" class="{{ $isAllocated ? 'text-red' : 'text-secondary' }}">#{{ str_pad((string)($i+1),2,'0',STR_PAD_LEFT) }}</span><span class="fw-bold" style="font-size:10px">{{ $choice }}</span></span>@empty<span class="text-secondary">—</span>@endforelse</div></td>
<td class="text-center align-middle">{{ $r->report_choice_position ?: '—' }}</td><td class="text-center align-middle">{{ collect(['CFF'=>$r->has_cff,'EM'=>$r->has_em,'PHC'=>$r->has_phc])->filter()->keys()->implode(', ') ?: 'Non Quota' }}</td><td class="text-center align-middle {{ (string)$r->allocated_post_code===(string)$post->post_code ? 'text-danger fw-bold' : '' }}">{{ $r->allocated_post_code ?: 'Unallocated' }}</td><td class="text-center align-middle">{{ $r->allocation_basis ?: '—' }}</td><td class="align-middle small">{{ $r->historical_exclusion_reason ?: '—' }}</td>
</tr>@endforeach</tbody></table></div><div class="card-footer">{{ $rows->links() }}</div></div>
@endsection
