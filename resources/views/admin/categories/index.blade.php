@extends('layouts.admin')

@section('title', 'Kategori')

@section('breadcrumb')
    @include('partials.breadcrumb', ['items' => [['label' => 'Kategori']]])
@endsection

@section('content')
    <x-admin.list-header :summary="number_format($categories->total(), 0, ',', '.').' kategori'.($search !== '' ? ' ditemukan' : '')">
        <a href="{{ route('admin.categories.create') }}" class="btn btn-accent btn-sm">
            <i class="bi bi-plus-lg"></i>Tambah Kategori
        </a>
    </x-admin.list-header>

    <x-admin.filters :action="route('admin.categories.index')" :reset-url="route('admin.categories.index')"
                     :active="$search !== '' ? 1 : 0" :show-reset="$search !== ''">
        <div class="col-12 col-md-6 col-lg">
            <label for="filter_q" class="form-label small mb-1">Cari kategori</label>
            <input type="search" id="filter_q" name="q" value="{{ $search }}" class="form-control form-control-sm"
                   placeholder="Nama kategori…" aria-label="Cari nama kategori">
        </div>
    </x-admin.filters>

    <div class="card">
        @if ($categories->isEmpty())
            @if ($search !== '')
                <x-empty-state icon="bi-search" title="Kategori tidak ditemukan" :message="'Tidak ada kategori yang cocok dengan “'.$search.'”.'" />
            @else
                <x-empty-state icon="bi-grid" title="Belum ada kategori" message="Tambahkan kategori seperti SUV, MPV, atau Sedan.">
                    <a href="{{ route('admin.categories.create') }}" class="btn btn-accent btn-sm"><i class="bi bi-plus-lg"></i>Tambah Kategori</a>
                </x-empty-state>
            @endif
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 admin-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px">No</th>
                            <th>Kategori</th>
                            <th class="text-center">Jumlah Mobil</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td class="text-center text-muted small">{{ $categories->firstItem() + $loop->index }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="admin-thumb admin-thumb-empty flex-shrink-0">
                                            <i class="bi bi-grid"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-truncate">{{ $category->name }}</div>
                                            <div class="small text-muted font-monospace">{{ $category->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if ($category->cars_count > 0)
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis fw-semibold">
                                            {{ $category->cars_count }} mobil
                                        </span>
                                    @else
                                        <span class="text-muted small">0 mobil</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @include('admin.partials.row-actions', [
                                        'editUrl' => route('admin.categories.edit', $category),
                                        'deleteUrl' => route('admin.categories.destroy', $category),
                                        'name' => $category->name,
                                        'disabledReason' => $category->cars_count > 0 ? "Masih dipakai {$category->cars_count} mobil" : null,
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pagination', ['paginator' => $categories])
        @endif
    </div>

    <x-delete-modal entity="kategori" />
@endsection
