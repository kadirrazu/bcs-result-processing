@extends('layouts.app')

@section('title', 'Create User')

@section('content')
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">
                        User Management
                    </div>

                    <h2 class="page-title">
                        Create User
                    </h2>
                </div>

                <div class="col-auto ms-auto">
                    <a
                        href="{{ route('users.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Back to Users
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <div class="card">
                <form method="POST" action="{{ route('users.store') }}">
                    @csrf

                    <div class="card-body">
                        @include('users._form')
                    </div>

                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-primary">
                            Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection