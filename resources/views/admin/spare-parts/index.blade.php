@extends('layouts.admin')

@section('title', 'Spare Part')

@section('breadcrumb')
    @include('partials.breadcrumb', ['items' => [['label' => 'Spare Part']]])
@endsection

@section('content')
    {{-- 4 Stat Card Inventaris --}}
    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-4">
        <div class="col">
            <x-stat-card label="Total Jenis Part" :value="$stats['total_items']" icon="bi-box-seam" color="navy" />
        </div>
        <div class="col">
            <x-stat-card label="Stok Tersedia" :value="$stats['in_stock_count']" icon="bi-check2-circle" color="green" />
        </div>
        <div class="col">
            <x-stat-card label="Stok Menipis" :value="$stats['low_stock_count']" icon="bi-exclamation-triangle" color="yellow" :alert="true" />
        </div>
        <div class="col">
            <x-stat-card label="Nilai Inventaris" :value="'Rp '.number_format($stats['total_inventory_value'], 0, ',', '.')" icon="bi-cash-stack" color="blue" />
        </div>
    </div>

    {{-- Filter & Tombol Tambah --}}
    <div class="card mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('admin.spare-parts.index') }}" class="row g-2 align-items-end" role="search">
                <div class="col-12 col-md-4 col-xl-3">
                    <label for="filter_q" class="form-label small mb-1">Cari Spare Part</label>
                    <input type="search" id="filter_q" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm"
                           placeholder="Kode part, nama, kecocokan…">
                </div>

                <div class="col-6 col-md-3 col-xl-2">
                    <label for="filter_kategori" class="form-label small mb-1">Kategori</label>
                    <select id="filter_kategori" name="kategori" class="form-select form-select-sm">
                        <option value="">Semua Kategori</option>
                        @foreach (\App\Models\SparePart::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected($filters['kategori'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3 col-xl-2">
                    <label for="filter_stok" class="form-label small mb-1">Status Stok</label>
                    <select id="filter_stok" name="stok" class="form-select form-select-sm">
                        <option value="">Semua Stok</option>
                        <option value="tersedia" @selected($filters['stok'] === 'tersedia')>Tersedia (&gt; 0)</option>
                        <option value="menipis" @selected($filters['stok'] === 'menipis')>Menipis (≤ Batas)</option>
                        <option value="habis" @selected($filters['stok'] === 'habis')>Habis (0)</option>
                    </select>
                </div>

                <div class="col-6 col-md-2 col-xl-2">
                    <label for="filter_status" class="form-label small mb-1">Status</label>
                    <select id="filter_status" name="status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="aktif" @selected($filters['status'] === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected($filters['status'] === 'nonaktif')>Nonaktif</option>
                    </select>
                </div>

                <div class="col-6 col-md-3 col-xl-2">
                    <label for="filter_urut" class="form-label small mb-1">Urutan</label>
                    <select id="filter_urut" name="urut" class="form-select form-select-sm">
                        <option value="terbaru" @selected($filters['urut'] === 'terbaru')>Terbaru</option>
                        <option value="nama_asc" @selected($filters['urut'] === 'nama_asc')>Nama (A–Z)</option>
                        <option value="harga_termurah" @selected($filters['urut'] === 'harga_termurah')>Harga Termurah</option>
                        <option value="harga_termahal" @selected($filters['urut'] === 'harga_termahal')>Harga Termahal</option>
                        <option value="stok_tersedikit" @selected($filters['urut'] === 'stok_tersedikit')>Stok Tersedikit</option>
                        <option value="stok_terbanyak" @selected($filters['urut'] === 'stok_terbanyak')>Stok Terbanyak</option>
                    </select>
                </div>

                <div class="col-12 col-xl-auto d-flex gap-2 ms-xl-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i>Terapkan</button>
                    @if ($hasFilters)
                        <a href="{{ route('admin.spare-parts.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                    @endif
                    @can('spare-parts.manage')
                        <a href="{{ route('admin.spare-parts.create') }}" class="btn btn-accent btn-sm text-nowrap">
                            <i class="bi bi-plus-lg"></i>Tambah Spare Part
                        </a>
                    @endcan
                </div>
            </form>
        </div>
    </div>

    {{-- Tabel Data Spare Part --}}
    <div class="card">
        @if ($spareParts->isEmpty())
            @if ($hasFilters)
                <x-empty-state icon="bi-search" title="Spare part tidak ditemukan" message="Tidak ada suku cadang yang sesuai dengan kriteria filter." />
            @else
                <x-empty-state icon="bi-box-seam" title="Belum ada spare part" message="Mulai tambahkan suku cadang seperti Filter Oli, Kampas Rem, atau Busi.">
                    @can('spare-parts.manage')
                        <a href="{{ route('admin.spare-parts.create') }}" class="btn btn-accent btn-sm">
                            <i class="bi bi-plus-lg"></i>Tambah Spare Part
                        </a>
                    @endcan
                </x-empty-state>
            @endif
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 admin-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px">No</th>
                            <th>Spare Part</th>
                            <th>Kategori</th>
                            <th>Kompatibilitas</th>
                            <th class="text-end">Harga</th>
                            <th class="text-center">Stok</th>
                            <th class="text-center">Status</th>
                            @can('spare-parts.manage')
                                <th class="text-end">Aksi</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($spareParts as $index => $part)
                            <tr>
                                <td class="text-center text-muted small">{{ $spareParts->firstItem() + $index }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($part->image)
                                            <img src="{{ $part->imageUrl() }}" alt="" width="40" height="40" class="rounded border object-fit-cover flex-shrink-0">
                                        @else
                                            <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted flex-shrink-0" style="width: 40px; height: 40px;">
                                                <i class="bi bi-box-seam"></i>
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-truncate" title="{{ $part->name }}">{{ $part->name }}</div>
                                            <div class="small text-muted font-monospace">{{ $part->part_number }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $part->categoryLabel() }}</span>
                                </td>
                                <td class="small">
                                    <span class="text-muted text-truncate d-inline-block" style="max-width: 220px;" title="{{ $part->compatibility ?: 'Universal / Semua' }}">
                                        {{ $part->compatibility ?: 'Universal' }}
                                    </span>
                                </td>
                                <td class="text-end fw-semibold text-nowrap">
                                    {{ $part->formattedPrice() }}
                                    <div class="small text-muted fw-normal">/ {{ $part->unit }}</div>
                                </td>
                                <td class="text-center text-nowrap">
                                    @if ($part->isOutOfStock())
                                        <span class="badge rounded-pill bg-danger-subtle text-danger fw-semibold">Habis (0)</span>
                                    @elseif ($part->isLowStock())
                                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-semibold" title="Batas minimum: {{ $part->min_stock }}">
                                            {{ $part->stock }} {{ $part->unit }} (Menipis)
                                        </span>
                                    @else
                                        <span class="badge rounded-pill bg-success-subtle text-success fw-semibold">
                                            {{ $part->stock }} {{ $part->unit }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @can('spare-parts.manage')
                                        <form method="POST" action="{{ route('admin.spare-parts.toggle-active', $part) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent" title="{{ $part->is_active ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan' }}">
                                                <span @class(['badge rounded-pill', 'bg-success' => $part->is_active, 'bg-secondary' => ! $part->is_active])>
                                                    {{ $part->is_active ? 'Aktif' : 'Nonaktif' }}
                                                </span>
                                            </button>
                                        </form>
                                    @else
                                        <span @class(['badge rounded-pill', 'bg-success' => $part->is_active, 'bg-secondary' => ! $part->is_active])>
                                            {{ $part->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    @endcan
                                </td>
                                @can('spare-parts.manage')
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('admin.spare-parts.edit', $part) }}" class="btn btn-sm btn-outline-primary btn-icon" title="Ubah">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.spare-parts.destroy', $part) }}" class="d-inline"
                                              data-confirm="Hapus spare part &quot;{{ $part->name }}&quot;? Tindakan ini tidak dapat dibatalkan.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($spareParts->hasPages())
                <div class="card-footer bg-transparent py-3">
                    {{ $spareParts->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
