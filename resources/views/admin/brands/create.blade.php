@extends('layouts.admin')

@section('title', 'Tambah Merek')

@section('breadcrumb')
    @include('partials.breadcrumb', ['items' => [
        ['label' => 'Merek', 'url' => route('admin.brands.index')],
        ['label' => 'Tambah'],
    ]])
@endsection

@section('content')
    <div class="card admin-form-card">
        <div class="card-body p-4">
            <h2 class="h5 mb-4">Informasi Merek</h2>
            <form method="POST" action="{{ route('admin.brands.store') }}" enctype="multipart/form-data" novalidate>
                @include('admin.brands._form')
            </form>
        </div>
    </div>
@endsection
