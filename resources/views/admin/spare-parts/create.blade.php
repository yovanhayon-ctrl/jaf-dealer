@extends('layouts.admin')

@section('title', 'Tambah Spare Part')

@section('breadcrumb')
    @include('partials.breadcrumb', ['items' => [
        ['label' => 'Spare Part', 'url' => route('admin.spare-parts.index')],
        ['label' => 'Tambah'],
    ]])
@endsection

@section('content')
    <div class="card admin-form-card">
        <div class="card-body p-4">
            <h2 class="h5 mb-4">Informasi Suku Cadang</h2>
            <form method="POST" action="{{ route('admin.spare-parts.store') }}" enctype="multipart/form-data">
                @include('admin.spare-parts._form')
            </form>
        </div>
    </div>
@endsection
