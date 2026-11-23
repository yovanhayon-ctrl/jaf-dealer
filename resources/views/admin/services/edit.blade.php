@extends('layouts.admin')

@section('title', 'Edit Layanan')

@section('breadcrumb')
    @include('partials.breadcrumb', ['items' => [
        ['label' => 'Layanan Servis', 'url' => route('admin.services.index')],
        ['label' => $service->name],
        ['label' => 'Ubah'],
    ]])
@endsection

@section('content')
    <div class="card admin-form-card">
        <div class="card-body p-4">
            <h2 class="h5 mb-4">Ubah Layanan: {{ $service->name }}</h2>
            <form method="POST" action="{{ route('admin.services.update', $service) }}" novalidate>
                @method('PUT')
                @include('admin.services._form')
            </form>
        </div>
    </div>
@endsection
