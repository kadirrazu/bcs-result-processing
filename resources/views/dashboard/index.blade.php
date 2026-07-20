@extends('layouts.app')

@section('title', 'Dashboard')

@section('page-header')
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">
                Overview
            </div>

            <h2 class="page-title">
                Dashboard
            </h2>
        </div>
    </div>
@endsection

@section('content')
    <div class="row row-deck row-cards">
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">
                        Application
                    </div>

                    <div class="h3 m-0">
                        Laravel {{ app()->version() }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">
                        Environment
                    </div>

                    <div class="h3 m-0">
                        {{ app()->environment() }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">
                        Authentication
                    </div>

                    <div class="h3 m-0 text-success">
                        Active
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">
                        Processing Baseline
                    </div>

                    <div class="h3 m-0">
                        Version 1.0
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">
                Environment setup completed
            </h3>
        </div>

        <div class="card-body">
            <p class="mb-2">
                Tabler UI and Laravel Fortify authentication are connected.
            </p>

            <p class="text-secondary mb-0">
                The business modules have not been implemented yet.
            </p>
        </div>
    </div>
@endsection