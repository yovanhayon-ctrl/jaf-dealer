@extends('layouts.admin')

@section('title', 'Edit Kategori')

@section('breadcrumb')
    @include('partials.breadcrumb', ['items' => [
        ['label' => 'Kategori', 'url' => route('admin.categories.index')],
        ['label' => $category->name],
        ['label' => 'Ubah'],
    ]])
@endsection

@section('content')
    <div class="card admin-form-card">
        <div class="card-body p-4">
            <h2 class="h5 mb-4">Ubah Kategori: {{ $category->name }}</h2>
            <form method="POST" action="{{ route('admin.categories.update', $category) }}" novalidate>
                @method('PUT')
                @include('admin.categories._form')
            </form>
        </div>
    </div>
@endsection
