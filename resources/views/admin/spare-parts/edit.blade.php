@extends('layouts.admin')

@section('title', 'Ubah Spare Part: '.$sparePart->name)

@section('breadcrumb')
    @include('partials.breadcrumb', ['items' => [
        ['label' => 'Spare Part', 'url' => route('admin.spare-parts.index')],
        ['label' => $sparePart->name],
        ['label' => 'Ubah'],
    ]])
@endsection

@section('content')
    <div class="card admin-form-card">
        <div class="card-body p-4">
            <h2 class="h5 mb-4">Ubah Suku Cadang: {{ $sparePart->name }}</h2>
            <form method="POST" action="{{ route('admin.spare-parts.update', $sparePart) }}" enctype="multipart/form-data">
                @method('PUT')
                @include('admin.spare-parts._form')
            </form>
        </div>
    </div>
@endsection
