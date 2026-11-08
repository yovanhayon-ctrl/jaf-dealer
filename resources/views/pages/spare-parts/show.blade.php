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

        <div class="row g-4 align-items-start mb-5">
            {{-- Kolom Kiri: Foto Suku Cadang & Deskripsi --}}
            <div class="col-lg-7">
                <div class="rounded-4 overflow-hidden border">
                    @if ($sparePart->image)
                        <img src="{{ $sparePart->imageUrl() }}" alt="{{ $title }}" class="car-gallery-img"
                             style="aspect-ratio: 4 / 3; object-fit: contain; background-color: var(--dm-bg);" loading="eager">
                    @else
                        <div class="car-gallery-empty" style="aspect-ratio: 4 / 3;" role="img" aria-label="Belum ada foto {{ $title }}">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    @endif
                </div>

                {{-- Deskripsi & Kompatibilitas --}}
                <div class="card mt-4">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Deskripsi & Spesifikasi</h2>

                        @if ($sparePart->compatibility)
                            <div class="mb-4">
                                <h3 class="h6 text-muted mb-1"><i class="bi bi-car-front me-1"></i>Kompatibilitas Kendaraan</h3>
                                <p class="mb-0 fw-semibold">{{ $sparePart->compatibility }}</p>
                            </div>
                        @endif

                        <div class="text-body" style="line-height: 1.7;">
                            @if ($sparePart->description)
                                {!! nl2br(e($sparePart->description)) !!}
                            @else
                                <p class="text-muted mb-0">Suku cadang orisinal kualitas terjamin. Dirancang khusus untuk kompatibilitas dan durabilitas maksimal sesuai standar pabrikan kendaraan Anda.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Rincian Produk, Harga, & Pemesanan --}}
            <div class="col-lg-5">
                <div class="car-detail-info">
                    <p class="text-muted mb-1">{{ $sparePart->categoryLabel() }} · <span class="font-monospace">SKU: {{ $sparePart->part_number }}</span></p>
                    <h1 class="h2 mb-2">{{ $sparePart->name }}</h1>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge rounded-pill badge-condition-used border">{{ $sparePart->categoryLabel() }}</span>
                        @if ($sparePart->isOutOfStock())
                            <span class="badge rounded-pill badge-status-red">Stok Habis</span>
                        @else
                            <span class="badge rounded-pill badge-condition-new">100% Genuine</span>
                        @endif
                    </div>

                    <div class="mb-2">
                        <div class="price-final car-detail-price fs-2">{{ $sparePart->formattedPrice() }}</div>
                        <p class="small text-muted mb-0">/ {{ $sparePart->unit }}</p>
                    </div>

                    <p class="mb-4">
                        @if ($sparePart->inStock())
                            <i class="bi bi-box-seam me-1"></i>Stok: <span class="fw-semibold">{{ $sparePart->stock }} {{ $sparePart->unit }}</span>
                        @else
                            <i class="bi bi-x-circle me-1 text-danger"></i><span class="fw-semibold text-danger">Stok habis</span>
                        @endif
                    </p>

                    <div class="d-flex flex-column gap-2 mb-4">
                        @if ($whatsappUrl)
                            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-whatsapp"></i> Pesan Sekarang via WhatsApp
                            </a>
                        @endif
                        <a href="{{ route('service-bookings.create') }}" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-wrench"></i> Booking Pasang di Bengkel
                        </a>
                    </div>

                    <div class="card">
                        <div class="card-body p-3">
                            <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                                <li class="d-flex align-items-center gap-2">
                                    <i class="bi bi-shield-check text-accent"></i>
                                    <span><strong>100% Asli:</strong> Suku cadang original bergaransi resmi.</span>
                                </li>
                                <li class="d-flex align-items-center gap-2">
                                    <i class="bi bi-tools text-accent"></i>
                                    <span><strong>Pemasangan Profesional:</strong> Teknisi bersertifikasi siap melayani di bengkel.</span>
                                </li>
                                <li class="d-flex align-items-center gap-2">
                                    <i class="bi bi-geo-alt text-accent"></i>
                                    <span><strong>Ambil di Dealer:</strong> Tersedia di jaringan bengkel dan dealer kami.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Suku Cadang Terkait --}}
        @if ($relatedParts->isNotEmpty())
            <div class="mt-5 border-top pt-4">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <div>
                        <h2 class="h4 mb-1">Suku Cadang Terkait</h2>
                        <p class="text-muted small mb-0">Rekomendasi part lain dalam kategori {{ $sparePart->categoryLabel() }}</p>
                    </div>
                    <a href="{{ route('spare-parts.index', ['kategori' => $sparePart->category]) }}" class="small fw-semibold">
                        Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>

                <div class="row g-4">
                    @foreach ($relatedParts as $related)
                        <div class="col-12 col-sm-6 col-lg-3">
                            @include('pages.spare-parts._card', ['part' => $related])
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
