@extends('layouts.admin')

@section('title', 'Merek')

@section('breadcrumb')
    @include('partials.breadcrumb', ['items' => [['label' => 'Merek']]])
@endsection

@section('content')
    <x-admin.list-header :summary="number_format($brands->total(), 0, ',', '.').' merek'.($search !== '' ? ' ditemukan' : '')">
        <a href="{{ route('admin.brands.create') }}" class="btn btn-accent btn-sm">
            <i class="bi bi-plus-lg"></i>Tambah Merek
        </a>
    </x-admin.list-header>

    <x-admin.filters :action="route('admin.brands.index')" :reset-url="route('admin.brands.index')"
                     :active="$search !== '' ? 1 : 0" :show-reset="$search !== ''">
        <div class="col-12 col-md-6 col-lg">
            <label for="filter_q" class="form-label small mb-1">Cari merek</label>
            <input type="search" id="filter_q" name="q" value="{{ $search }}" class="form-control form-control-sm"
                   placeholder="Nama merek…" aria-label="Cari nama merek">
        </div>
    </x-admin.filters>

    <div class="card">
        @if ($brands->isEmpty())
            @if ($search !== '')
                <x-empty-state icon="bi-search" title="Merek tidak ditemukan" :message="'Tidak ada merek yang cocok dengan “'.$search.'”.'" />
            @else
                <x-empty-state icon="bi-tags" title="Belum ada merek" message="Tambahkan merek pertama untuk mulai mengisi data mobil.">
                    <a href="{{ route('admin.brands.create') }}" class="btn btn-accent btn-sm"><i class="bi bi-plus-lg"></i>Tambah Merek</a>
                </x-empty-state>
            @endif
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 admin-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px">No</th>
                            <th>Merek</th>
                            <th class="text-center">Jumlah Mobil</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($brands as $brand)
                            <tr>
                                <td class="text-center text-muted small">{{ $brands->firstItem() + $loop->index }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        @if ($brand->logo_url)
                                            <img src="{{ $brand->logo_url }}" alt="Logo {{ $brand->name }}" class="admin-thumb p-1 object-fit-contain flex-shrink-0">
                                        @else
                                            <span class="admin-thumb admin-thumb-empty flex-shrink-0"><i class="bi bi-image"></i></span>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-truncate">{{ $brand->name }}</div>
                                            <div class="small text-muted font-monospace">{{ $brand->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if ($brand->cars_count > 0)
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis fw-semibold">
                                            {{ $brand->cars_count }} mobil
                                        </span>
                                    @else
                                        <span class="text-muted small">0 mobil</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @include('admin.partials.row-actions', [
                                        'editUrl' => route('admin.brands.edit', $brand),
                                        'deleteUrl' => route('admin.brands.destroy', $brand),
                                        'name' => $brand->name,
                                        'disabledReason' => $brand->cars_count > 0 ? "Masih dipakai {$brand->cars_count} mobil" : null,
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pagination', ['paginator' => $brands])
        @endif
    </div>

    <x-delete-modal entity="merek" />
@endsection
