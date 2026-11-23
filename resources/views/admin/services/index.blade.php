@extends('layouts.admin')

@section('title', 'Layanan Servis')

@section('breadcrumb')
    @include('partials.breadcrumb', ['items' => [['label' => 'Layanan Servis']]])
@endsection

@section('content')
    <x-admin.list-header :summary="number_format($services->total(), 0, ',', '.').' layanan'.($hasFilters ? ' ditemukan' : '')">
        <a href="{{ route('admin.services.create') }}" class="btn btn-accent btn-sm">
            <i class="bi bi-plus-lg"></i>Tambah Layanan
        </a>
    </x-admin.list-header>

    @php
        $activeFilters = ($search !== '' ? 1 : 0) + ($status !== null ? 1 : 0);
    @endphp
    <x-admin.filters :action="route('admin.services.index')" :reset-url="route('admin.services.index')"
                     :active="$activeFilters" :show-reset="$hasFilters">
        <div class="col-12 col-md-6 col-lg">
            <label for="filter_q" class="form-label small mb-1">Cari layanan</label>
            <input type="search" id="filter_q" name="q" value="{{ $search }}" class="form-control form-control-sm"
                   placeholder="Nama layanan…" aria-label="Cari nama layanan">
        </div>
        <div class="col-12 col-sm-6 col-lg-auto">
            <label for="filter_status" class="form-label small mb-1">Status</label>
            <select id="filter_status" name="status" class="form-select form-select-sm" aria-label="Filter status">
                <option value="">Semua status</option>
                <option value="aktif" @selected($status === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
    </x-admin.filters>

    <div class="card">
        @if ($services->isEmpty())
            @if ($hasFilters)
                <x-empty-state icon="bi-search" title="Layanan tidak ditemukan" message="Tidak ada layanan yang cocok dengan filter." />
            @else
                <x-empty-state icon="bi-tools" title="Belum ada layanan" message="Tambahkan layanan seperti Servis Berkala atau Inspeksi Kendaraan.">
                    <a href="{{ route('admin.services.create') }}" class="btn btn-accent btn-sm"><i class="bi bi-plus-lg"></i>Tambah Layanan</a>
                </x-empty-state>
            @endif
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 admin-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px">No</th>
                            <th>Layanan</th>
                            <th class="text-end">Harga Mulai</th>
                            <th class="text-center">Durasi</th>
                            <th class="text-center">Jumlah Booking</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($services as $service)
                            <tr>
                                <td class="text-center text-muted small">{{ $services->firstItem() + $loop->index }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="admin-thumb admin-thumb-empty flex-shrink-0">
                                            <i class="bi bi-tools"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-truncate">{{ $service->name }}</div>
                                            <div class="small text-muted font-monospace">{{ $service->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($service->price_from)
                                        Rp {{ number_format($service->price_from, 0, ',', '.') }}
                                    @else
                                        <span class="text-muted">Hubungi dealer</span>
                                    @endif
                                </td>
                                <td class="text-center text-nowrap">{{ $service->duration_minutes ? $service->duration_minutes.' menit' : '—' }}</td>
                                <td class="text-center">
                                    @if ($service->bookings_count > 0)
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis fw-semibold">
                                            {{ $service->bookings_count }}
                                        </span>
                                    @else
                                        <span class="text-muted small">0</span>
                                    @endif
                                </td>
                                <td><x-status-badge :status="$service->is_active ? 'active' : 'inactive'" /></td>
                                <td class="text-end text-nowrap">
                                    @include('admin.partials.row-actions', [
                                        'editUrl' => route('admin.services.edit', $service),
                                        'deleteUrl' => route('admin.services.destroy', $service),
                                        'name' => $service->name,
                                        'disabledReason' => $service->bookings_count > 0 ? "Sudah dipakai {$service->bookings_count} booking. Nonaktifkan saja." : null,
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pagination', ['paginator' => $services])
        @endif
    </div>

    <x-delete-modal entity="layanan" />
@endsection
