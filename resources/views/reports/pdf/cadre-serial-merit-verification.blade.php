<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
@include('reports.pdf.cadre-serial-merit-verification-style')
.page { page-break-after: always; }
.page.last { page-break-after: auto; }
</style>
</head>
<body>
@php
    $totalPageCount = $sections->sum(fn ($section) => $section['pages']->count());
    $renderedPage = 0;
@endphp

@foreach($sections as $section)
    @foreach($section['pages'] as $pageIndex => $page)
        @php
            $renderedPage++;
            $groups = $page['groups'];
            $maxRows = collect($groups)->map(fn ($group) => $group->count())->max() ?? 0;
        @endphp
        <div class="page {{ $renderedPage === $totalPageCount ? 'last' : '' }}">
            @include('reports.pdf.cadre-serial-merit-verification-page', [
                'examinationName' => $examinationName,
                'title' => $title,
                'section' => $section,
                'pageIndex' => $pageIndex,
                'groups' => $groups,
                'maxRows' => $maxRows,
                'generatedAt' => $generatedAt ?? now(),
            ])
        </div>
    @endforeach
@endforeach
</body>
</html>
