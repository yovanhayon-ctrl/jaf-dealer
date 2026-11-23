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
            {{-- Kolom Kiri: Foto Suku Cadang (col-lg-5) --}}
            <div class="col-lg-5">
                <div class="spare-part-detail-stage shadow-sm">
                    @if ($sparePart->image)
                        <img src="{{ $sparePart->imageUrl() }}" alt="{{ $title }}" loading="eager" referrerpolicy="no-referrer">
                    @else
                        <div class="spare-part-detail-stage-empty" role="img" aria-label="Belum ada foto {{ $title }}">
                            <i class="bi bi-box-seam"></i>
                            <span class="small mt-2 fs-6">Belum ada foto suku cadang</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Kolom Kanan: Rincian Produk, Harga, & Pemesanan (col-lg-7) --}}
            <div class="col-lg-7">
                <div class="car-detail-info">
                    <p class="text-muted mb-1">{{ $sparePart->categoryLabel() }} · <span class="font-monospace">SKU: {{ $sparePart->part_number }}</span></p>
                    <h1 class="h2 mb-2">{{ $sparePart->name }}</h1>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge-subtle-soft">{{ $sparePart->categoryLabel() }}</span>
                        @if ($sparePart->isOutOfStock())
                            <span class="badge rounded-pill badge-status-red" style="font-size: 12px; padding: 4px 10px;">Stok Habis</span>
                        @else
                            <span class="badge-subtle-soft is-success"><i class="bi bi-shield-check me-1"></i>100% Genuine</span>
                        @endif
                    </div>

                    <div class="d-flex align-items-baseline gap-2 mb-2">
                        <span class="price-final car-detail-price fs-2">{{ $sparePart->formattedPrice() }}</span>
                        <span class="text-muted fs-5">/ {{ $sparePart->unit }}</span>
                    </div>

                    <p class="mb-3 text-body">
                        @if ($sparePart->inStock())
                            <i class="bi bi-box-seam me-1"></i>Stok: <span class="fw-semibold">{{ $sparePart->stock }} {{ $sparePart->unit }}</span>
                        @else
                            <i class="bi bi-x-circle me-1 text-danger"></i><span class="fw-semibold text-danger">Stok habis</span>
                        @endif
                    </p>

                    {{-- Quantity Selector & Estimasi Subtotal --}}
                    @if ($sparePart->inStock())
                        <div class="p-3 rounded-3 bg-body-tertiary border mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label for="partQtyInput" class="small fw-semibold text-muted mb-0">Jumlah Pesanan</label>
                                <span class="small text-muted">Maks. {{ $sparePart->stock }} {{ $sparePart->unit }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <div class="quantity-picker" style="width: fit-content; display: inline-flex; align-items: center;">
                                    <button class="btn btn-outline-secondary" type="button" id="btnQtyMinus" aria-label="Kurangi jumlah" style="width: 36px; height: 36px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number" id="partQtyInput" class="form-control"
                                           value="1" min="1" max="{{ $sparePart->stock }}"
                                           style="width: 52px; height: 36px; text-align: center; font-weight: 700;"
                                           data-price="{{ $sparePart->price }}"
                                           data-stock="{{ $sparePart->stock }}"
                                           data-unit="{{ $sparePart->unit }}"
                                           data-sku="{{ $sparePart->part_number }}"
                                           data-name="{{ $sparePart->name }}"
                                           data-wa-base="{{ $waBaseUrl ?? '' }}"
                                           data-service-url="{{ route('service-bookings.create', ['part_id' => $sparePart->id]) }}"
                                           data-dealer-name="{{ config('dealer.name') ?: 'JAF Dealer' }}"
                                           data-detail-url="{{ route('spare-parts.show', $sparePart) }}"
                                           aria-label="Jumlah pesanan">
                                    <button class="btn btn-outline-secondary" type="button" id="btnQtyPlus" aria-label="Tambah jumlah" style="width: 36px; height: 36px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                                <div class="text-end" style="white-space: nowrap;">
                                    <span class="small text-muted d-block" style="font-size: 0.8rem;">Estimasi Subtotal:</span>
                                    <span class="fs-5 fw-bold text-accent" id="partSubtotalDisplay" style="white-space: nowrap;">
                                        {{ $sparePart->formattedPrice() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-danger d-flex align-items-center small py-2 mb-4" role="alert">
                            <i class="bi bi-x-circle-fill me-2 fs-6"></i>
                            <div>Stok suku cadang saat ini sedang habis. Silakan hubungi dealer untuk informasi ketersediaan.</div>
                        </div>
                    @endif

                    <div class="d-flex flex-column mb-4" style="gap: 10px;">
                        @if ($sparePart->inStock())
                            @if ($whatsappUrl)
                                <a href="{{ $whatsappUrl }}" id="btnWhatsAppOrder" target="_blank" rel="noopener" class="btn btn-primary btn-lg w-100">
                                    <i class="bi bi-whatsapp"></i> Pesan Sekarang via WhatsApp
                                </a>
                            @endif
                            <a href="{{ route('service-bookings.create', ['part_id' => $sparePart->id, 'qty' => 1]) }}" id="btnBookingService" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-wrench"></i> Booking Pasang di Bengkel
                            </a>
                        @else
                            <button class="btn btn-secondary btn-lg w-100" disabled>
                                <i class="bi bi-slash-circle"></i> Stok Habis
                            </button>
                            @if ($whatsappUrl)
                                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-whatsapp"></i> Tanya Ketersediaan via WhatsApp
                                </a>
                            @endif
                        @endif
                    </div>

                    {{-- Trust Badges Ringkas --}}
                    <div class="p-2 px-3 rounded-3 bg-body-tertiary border small d-flex flex-column gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-shield-check text-accent fs-6"></i>
                            <span><strong>100% Asli:</strong> Suku cadang original bergaransi resmi.</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-tools text-accent fs-6"></i>
                            <span><strong>Pemasangan Profesional:</strong> Teknisi bersertifikasi siap melayani di bengkel.</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-geo-alt text-accent fs-6"></i>
                            <span><strong>Ambil di Dealer:</strong> Tersedia di jaringan bengkel dan dealer kami.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kotak Deskripsi & Spesifikasi Teknis --}}
        <div class="card rounded-4 border shadow-sm mb-5">
            <div class="card-header bg-transparent py-3 border-bottom d-flex align-items-center">
                <i class="bi bi-card-text text-accent fs-5 me-2"></i>
                <h2 class="h5 mb-0 fw-bold">Deskripsi &amp; Spesifikasi Teknis</h2>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    {{-- Kolom Kiri: Penjelasan Produk & Kompatibilitas --}}
                    <div class="col-lg-7">
                        <h3 class="h6 fw-bold mb-3 text-accent"><i class="bi bi-info-circle me-1"></i>Penjelasan Produk</h3>
                        <div class="text-body mb-4" style="line-height: 1.8;">
                            @if ($sparePart->description)
                                {!! nl2br(e($sparePart->description)) !!}
                            @else
                                <p class="text-muted mb-0">Suku cadang orisinal kualitas terjamin. Dirancang khusus untuk kompatibilitas dan durabilitas maksimal sesuai standar pabrikan kendaraan Anda.</p>
                            @endif
                        </div>

                        <div class="p-3 rounded-3 bg-body-tertiary border">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-car-front-fill text-accent fs-5 mt-1"></i>
                                <div>
                                    <strong class="d-block mb-1">Kompatibilitas Kendaraan:</strong>
                                    <span class="text-body">{{ $sparePart->compatibility ?: 'Universal / Sesuai Spesifikasi' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Kolom Kanan: Rincian Spesifikasi Teknis (Tabel Bersih) --}}
                    <div class="col-lg-5">
                        <h3 class="h6 fw-bold mb-3 text-accent"><i class="bi bi-list-check me-1"></i>Rincian Teknis</h3>
                        <div class="p-3 rounded-3 bg-body-tertiary border">
                            <div class="spare-part-spec-list">
                                <div class="spare-part-spec-row">
                                    <span class="spare-part-spec-label">Nomor Part (SKU)</span>
                                    <span class="spare-part-spec-value font-monospace fw-bold">{{ $sparePart->part_number }}</span>
                                </div>
                                <div class="spare-part-spec-row">
                                    <span class="spare-part-spec-label">Kategori</span>
                                    <span class="spare-part-spec-value">{{ $sparePart->categoryLabel() }}</span>
                                </div>
                                <div class="spare-part-spec-row">
                                    <span class="spare-part-spec-label">Satuan Kemasan</span>
                                    <span class="spare-part-spec-value">Per {{ $sparePart->unit }}</span>
                                </div>
                                <div class="spare-part-spec-row">
                                    <span class="spare-part-spec-label">Standar Kualitas</span>
                                    <span class="spare-part-spec-value text-success fw-semibold"><i class="bi bi-shield-check me-1"></i>Nissan Genuine / OEM</span>
                                </div>
                                <div class="spare-part-spec-row">
                                    <span class="spare-part-spec-label">Status Stok</span>
                                    <span class="spare-part-spec-value">
                                        @if ($sparePart->inStock())
                                            <span class="badge-subtle-soft is-success">Tersedia ({{ $sparePart->stock }} {{ $sparePart->unit }})</span>
                                        @else
                                            <span class="badge rounded-pill badge-status-red" style="font-size: 11px; padding: 3px 8px;">Stok Habis</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
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

@push('scripts')
    <script src="{{ \App\Support\Asset::url('js/spare-part.js') }}"></script>
@endpush
