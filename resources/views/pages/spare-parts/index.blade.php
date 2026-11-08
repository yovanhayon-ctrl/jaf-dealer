@extends('layouts.app')

@section('title', 'Katalog Suku Cadang & Aksesori')
@section('meta_description', 'Katalog suku cadang dan aksesori resmi di '.config('dealer.name').': sedia filter oli, kampas rem, busi, pelumas mesin, aki, dan spare part orisinal berkualitas.')

@php
    $whatsapp = config('dealer.whatsapp');
@endphp

@section('content')
    {{-- Hero Section --}}
    <section class="home-hero py-5" aria-labelledby="sparepart-title">
        <div class="container py-lg-3">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <p class="hero-kicker"><i class="bi bi-box-seam me-1"></i>JAF Genuine Parts</p>
                    <h1 id="sparepart-title" class="display-6 fw-bold text-white mb-3">Suku Cadang & Aksesori Resmi</h1>
                    <p class="lead mb-4">
                        Jaga performa, efisiensi, dan keselamatan kendaraan Anda dengan suku cadang asli bergaransi.
                        Tersedia untuk berbagai model kendaraan dengan jaminan kualitas terbaik.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        @if ($whatsapp)
                            <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Halo '.config('dealer.name').', saya ingin konsultasi ketersediaan suku cadang kendaraan.') }}"
                               target="_blank" rel="noopener" class="btn btn-accent btn-lg">
                                <i class="bi bi-whatsapp"></i>Tanya Spare Part via WhatsApp
                            </a>
                        @endif
                        <a href="{{ route('service-bookings.create') }}" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-wrench"></i>Booking Pasang di Bengkel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container py-4">
        @include('partials.public-breadcrumb', ['items' => [['label' => 'Spare Part']]])

        {{-- Filter & Kategori --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3 p-lg-4">
                {{-- Kategori Horizontal Pills --}}
                <div class="d-flex align-items-center gap-2 overflow-x-auto pb-2 mb-3 text-nowrap">
                    <a href="{{ route('spare-parts.index', array_filter([...$filters, 'kategori' => null])) }}"
                       @class(['btn btn-sm rounded-pill', 'btn-primary' => empty($filters['kategori']), 'btn-outline-secondary' => ! empty($filters['kategori'])])>
                        Semua Kategori <span class="badge bg-white text-dark ms-1">{{ $totalActive }}</span>
                    </a>
                    @foreach ($categories as $catKey => $catLabel)
                        @php($count = $categoryCounts[$catKey] ?? 0)
                        @continue($count === 0)
                        <a href="{{ route('spare-parts.index', array_filter([...$filters, 'kategori' => $catKey])) }}"
                           @class(['btn btn-sm rounded-pill', 'btn-primary' => ($filters['kategori'] === $catKey), 'btn-outline-secondary' => ($filters['kategori'] !== $catKey)])>
                            {{ $catLabel }} <span class="badge bg-white text-dark ms-1">{{ $count }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- Form Pencarian & Opsi Filter --}}
                <form method="GET" action="{{ route('spare-parts.index') }}" class="row g-2 align-items-center">
                    @if (! empty($filters['kategori']))
                        <input type="hidden" name="kategori" value="{{ $filters['kategori'] }}">
                    @endif

                    <div class="col-12 col-md">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control border-start-0 ps-0"
                                   placeholder="Cari nama suku cadang, nomor part, model mobil…">
                        </div>
                    </div>

                    <div class="col-6 col-md-auto">
                        <div class="form-check form-switch pt-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="filter_tersedia" name="tersedia" value="1"
                                   @checked($filters['tersedia']) onchange="this.form.submit()">
                            <label class="form-check-label small" for="filter_tersedia">Hanya stok ada</label>
                        </div>
                    </div>

                    <div class="col-6 col-md-auto">
                        <select name="urut" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="terbaru" @selected($filters['urut'] === 'terbaru')>Terbaru</option>
                            <option value="termurah" @selected($filters['urut'] === 'termurah')>Harga: Rendah ke Tinggi</option>
                            <option value="termahal" @selected($filters['urut'] === 'termahal')>Harga: Tinggi ke Rendah</option>
                            <option value="nama_asc" @selected($filters['urut'] === 'nama_asc')>Nama: A ke Z</option>
                        </select>
                    </div>

                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">Cari</button>
                        @if ($hasFilters)
                            <a href="{{ route('spare-parts.index') }}" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        {{-- Daftar Produk Grid --}}
        @if ($spareParts->isEmpty())
            <div class="card border-0 shadow-sm">
                <x-empty-state icon="bi-box-seam" title="Suku cadang tidak ditemukan"
                               message="Tidak ada suku cadang yang sesuai dengan kriteria pencarian atau filter Anda.">
                    @if ($hasFilters)
                        <a href="{{ route('spare-parts.index') }}" class="btn btn-outline-primary btn-sm mt-2">
                            Lihat Semua Suku Cadang
                        </a>
                    @endif
                </x-empty-state>
            </div>
        @else
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3 g-lg-4">
                @foreach ($spareParts as $part)
                    <div class="col">
                        <article class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden position-relative product-card">
                            {{-- Foto Spare Part --}}
                            <a href="{{ route('spare-parts.show', $part) }}" class="d-block position-relative bg-light text-decoration-none ratio ratio-4x3 overflow-hidden">
                                @if ($part->image)
                                    <img src="{{ $part->imageUrl() }}" alt="{{ $part->name }}" class="object-fit-cover w-100 h-100 transition-transform">
                                @else
                                    <div class="d-flex flex-column align-items-center justify-content-center text-muted h-100">
                                        <i class="bi bi-box-seam fs-1 mb-1"></i>
                                        <span class="small font-monospace text-secondary">{{ $part->part_number }}</span>
                                    </div>
                                @endif

                                {{-- Badge Kategori --}}
                                <span class="position-absolute top-0 start-0 m-2 badge bg-dark bg-opacity-75 rounded-pill px-2 py-1 small">
                                    {{ $part->categoryLabel() }}
                                </span>
                            </a>

                            {{-- Card Body --}}
                            <div class="card-body p-3 d-flex flex-column">
                                <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                    <span class="small text-muted font-monospace text-truncate" title="{{ $part->part_number }}">
                                        {{ $part->part_number }}
                                    </span>
                                    @if ($part->isOutOfStock())
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-0 small">Habis</span>
                                    @elseif ($part->isLowStock())
                                        <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2 py-0 small">Sisa {{ $part->stock }}</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0 small">Tersedia</span>
                                    @endif
                                </div>

                                <h2 class="h6 card-title mb-2">
                                    <a href="{{ route('spare-parts.show', $part) }}" class="text-reset text-decoration-none text-truncate-2" title="{{ $part->name }}">
                                        {{ $part->name }}
                                    </a>
                                </h2>

                                @if ($part->compatibility)
                                    <p class="small text-muted text-truncate mb-3" title="Cocok untuk: {{ $part->compatibility }}">
                                        <i class="bi bi-car-front me-1"></i>{{ $part->compatibility }}
                                    </p>
                                @endif

                                <div class="mt-auto pt-2 border-top d-flex align-items-center justify-content-between gap-2">
                                    <div>
                                        <div class="fw-bold text-accent fs-6">{{ $part->formattedPrice() }}</div>
                                        <div class="small text-muted">/ {{ $part->unit }}</div>
                                    </div>
                                    @if ($orderUrl = $part->whatsappOrderUrl())
                                        <a href="{{ $orderUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Pesan via WhatsApp">
                                            <i class="bi bi-whatsapp"></i>Pesan
                                        </a>
                                    @else
                                        <a href="{{ route('spare-parts.show', $part) }}" class="btn btn-sm btn-outline-primary">
                                            Detail
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if ($spareParts->hasPages())
                <div class="mt-4 pt-2 d-flex justify-content-center">
                    {{ $spareParts->onEachSide(1)->links('partials.pagination-links') }}
                </div>
            @endif
        @endif
    </div>
@endsection
