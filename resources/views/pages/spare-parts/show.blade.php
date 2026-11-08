@extends('layouts.app')

@php
    $title = $sparePart->name;
    $description = trim((string) ($sparePart->description ?: "Suku cadang {$sparePart->name} (Kode: {$sparePart->part_number}) untuk kendaraan di ".config('dealer.name').'.'));
    $metaDescription = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish(strip_tags($description)), 155);
    $detailUrl = route('spare-parts.show', $sparePart);

    $structuredData = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $sparePart->name,
        'sku' => $sparePart->part_number,
        'mpn' => $sparePart->part_number,
        'category' => $sparePart->categoryLabel(),
        'description' => $metaDescription,
        'image' => $sparePart->imageUrl() ? [$sparePart->imageUrl()] : null,
        'url' => $detailUrl,
        'offers' => [
            '@type' => 'Offer',
            'price' => $sparePart->price,
            'priceCurrency' => 'IDR',
            'availability' => $sparePart->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => $detailUrl,
            'seller' => ['@type' => 'AutoDealer', 'name' => config('dealer.name')],
        ],
    ]);
@endphp

@section('title', $title)
@section('meta_description', $metaDescription)

@section('og_type', 'product')
@if ($sparePart->image)
    @section('og_image', $sparePart->imageUrl())
@endif

@push('meta')
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <div class="container py-4">
        @include('partials.public-breadcrumb', ['items' => [
            ['label' => 'Spare Part', 'url' => route('spare-parts.index')],
            ['label' => $title],
        ]])

        <div class="row g-4 g-lg-5 mb-5">
            {{-- Kolom Kiri: Foto Suku Cadang --}}
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden sticky-top" style="top: 5.5rem;">
                    <div class="ratio ratio-1x1 bg-light d-flex align-items-center justify-content-center p-3">
                        @if ($sparePart->image)
                            <img src="{{ $sparePart->imageUrl() }}" alt="{{ $sparePart->name }}" class="object-fit-contain w-100 h-100 p-2">
                        @else
                            <div class="d-flex flex-column align-items-center justify-content-center text-muted">
                                <i class="bi bi-box-seam display-1 mb-2"></i>
                                <span class="font-monospace text-secondary">{{ $sparePart->part_number }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                        <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2 rounded-pill">
                            <i class="bi bi-tag me-1"></i>{{ $sparePart->categoryLabel() }}
                        </span>
                        @if ($sparePart->isOutOfStock())
                            <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill">
                                <i class="bi bi-x-circle me-1"></i>Stok Habis
                            </span>
                        @elseif ($sparePart->isLowStock())
                            <span class="badge bg-warning-subtle text-warning-emphasis px-3 py-2 rounded-pill">
                                <i class="bi bi-exclamation-triangle me-1"></i>Stok Menipis (Sisa {{ $sparePart->stock }} {{ $sparePart->unit }})
                            </span>
                        @else
                            <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill">
                                <i class="bi bi-check-circle me-1"></i>Tersedia ({{ $sparePart->stock }} {{ $sparePart->unit }})
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Rincian & Pemesanan --}}
            <div class="col-lg-7">
                <div class="d-flex flex-column gap-3">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge bg-dark bg-opacity-75 font-monospace px-3 py-1">
                                SKU: {{ $sparePart->part_number }}
                            </span>
                            <span class="badge bg-light text-dark border">100% Genuine Parts</span>
                        </div>
                        <h1 class="h2 fw-bold mb-2">{{ $sparePart->name }}</h1>
                    </div>

                    {{-- Harga --}}
                    <div class="p-3 bg-light rounded-3 d-flex align-items-baseline gap-2">
                        <span class="display-6 fw-bold text-accent">{{ $sparePart->formattedPrice() }}</span>
                        <span class="text-muted fs-6">/ {{ $sparePart->unit }}</span>
                    </div>

                    {{-- Tombol Aksi Pemesanan --}}
                    <div class="d-flex flex-column flex-sm-row gap-2 pt-2">
                        @if ($whatsappUrl)
                            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-success btn-lg flex-grow-1 d-inline-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-whatsapp fs-5"></i>Pesan Sekarang via WhatsApp
                            </a>
                        @endif
                        <a href="{{ route('service-bookings.create') }}" class="btn btn-outline-primary btn-lg d-inline-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-wrench"></i>Booking Pasang di Bengkel
                        </a>
                    </div>

                    {{-- Kompatibilitas Kendaraan --}}
                    @if ($sparePart->compatibility)
                        <div class="card border-0 bg-info-subtle text-info-emphasis rounded-3 mt-2">
                            <div class="card-body p-3 d-flex gap-3 align-items-start">
                                <i class="bi bi-car-front fs-4 flex-shrink-0 text-info"></i>
                                <div>
                                    <h2 class="h6 fw-semibold mb-1">Kompatibilitas Kendaraan</h2>
                                    <p class="small mb-0">{{ $sparePart->compatibility }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Deskripsi Suku Cadang --}}
                    <div class="pt-2">
                        <h2 class="h5 fw-bold mb-2">Deskripsi Produk</h2>
                        <div class="text-muted" style="line-height: 1.7;">
                            @if ($sparePart->description)
                                {!! nl2br(e($sparePart->description)) !!}
                            @else
                                <p>Suku cadang orisinal kualitas terjamin. Dirancang khusus untuk kompatibilitas dan durabilitas maksimal sesuai standar kendaraan Anda.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Keunggulan / Garansi --}}
                    <div class="border-top pt-3 mt-2">
                        <div class="row row-cols-1 row-cols-sm-3 g-3 text-center">
                            <div class="col">
                                <div class="p-2">
                                    <i class="bi bi-shield-check text-primary fs-3 mb-1 d-block"></i>
                                    <div class="fw-semibold small">100% Asli</div>
                                    <div class="text-muted small">Jaminan part resmi</div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="p-2">
                                    <i class="bi bi-tools text-primary fs-3 mb-1 d-block"></i>
                                    <div class="fw-semibold small">Teknisi Ahli</div>
                                    <div class="text-muted small">Bisa pasang di bengkel</div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="p-2">
                                    <i class="bi bi-truck text-primary fs-3 mb-1 d-block"></i>
                                    <div class="fw-semibold small">Siap Kirim / Ambil</div>
                                    <div class="text-muted small">Ambil di showroom kami</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Suku Cadang Terkait --}}
        @if ($relatedParts->isNotEmpty())
            <div class="border-top pt-5">
                <div class="d-flex justify-content-between align-items-end mb-4">
                    <div>
                        <h2 class="h4 fw-bold mb-1">Suku Cadang Terkait</h2>
                        <p class="text-muted small mb-0">Rekomendasi part lain dalam kategori {{ $sparePart->categoryLabel() }}</p>
                    </div>
                    <a href="{{ route('spare-parts.index', ['kategori' => $sparePart->category]) }}" class="btn btn-outline-secondary btn-sm">
                        Lihat Kategori Ini <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>

                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-3 g-lg-4">
                    @foreach ($relatedParts as $related)
                        <div class="col">
                            <article class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden product-card">
                                <a href="{{ route('spare-parts.show', $related) }}" class="d-block ratio ratio-4x3 bg-light text-decoration-none">
                                    @if ($related->image)
                                        <img src="{{ $related->imageUrl() }}" alt="{{ $related->name }}" class="object-fit-cover w-100 h-100">
                                    @else
                                        <div class="d-flex flex-column align-items-center justify-content-center text-muted h-100">
                                            <i class="bi bi-box-seam fs-2 mb-1"></i>
                                            <span class="small font-monospace">{{ $related->part_number }}</span>
                                        </div>
                                    @endif
                                </a>
                                <div class="card-body p-3 d-flex flex-column">
                                    <span class="small text-muted font-monospace mb-1">{{ $related->part_number }}</span>
                                    <h3 class="h6 card-title mb-2">
                                        <a href="{{ route('spare-parts.show', $related) }}" class="text-reset text-decoration-none text-truncate-2">
                                            {{ $related->name }}
                                        </a>
                                    </h3>
                                    <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-accent">{{ $related->formattedPrice() }}</span>
                                        <a href="{{ route('spare-parts.show', $related) }}" class="btn btn-sm btn-outline-primary">
                                            Detail
                                        </a>
                                    </div>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
