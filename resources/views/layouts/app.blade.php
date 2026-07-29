<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
    @php
        $activeExamination = app(\App\Support\Examinations\ExaminationContext::class)->current();
    @endphp
    <div class="page">
        @include('layouts.partials.topbar', ['activeExamination' => $activeExamination])
        @include('layouts.partials.examination-navigation', ['activeExamination' => $activeExamination])
        <div class="page-wrapper">
            @hasSection('page-header')
                <div class="page-header d-print-none"><div class="container-xl">@yield('page-header')</div></div>
            @endif
            <div class="page-body">
                <div class="container-xl">
                    @include('layouts.partials.flash-messages')
                    @yield('content')
                </div>
            </div>
            @include('layouts.partials.footer')
        </div>
    </div>
    @stack('scripts')
</body>
</html>
