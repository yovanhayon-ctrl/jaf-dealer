@extends('layouts.app')

@php
    $images = $car->images;
    $description = trim((string) $car->description);
    $metaDescription = $description !== ''
        ? \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish(strip_tags($description)), 155)
        : "{$title} ({$car->condition_label}), harga Rp ".number_format($finalPrice, 0, ',', '.').' di '.config('dealer.name').'.';
    $detailUrl = route('cars.show', $car);
    $purchaseRoute = Route::has('purchase-requests.create');
    $testDriveRoute = Route::has('test-drives.create');

    $colorSwatches = $car->colorSwatches();

    // Petakan masing-masing swatch warna ke indeks gambar mobil:
    // 1. Cek apakah ada foto yang secara eksplisit diset warnanya sama dengan swatch
    // 2. Jika tidak ada, gunakan indeks fallback berdasarkan urutan gambar
    $swatchImageMap = [];
    foreach ($colorSwatches as $sIndex => $swatch) {
        $matchedImgIndex = $images->search(fn ($img) => $img->color_name === $swatch['name']);
        if ($matchedImgIndex !== false) {
            $swatchImageMap[$sIndex] = $matchedImgIndex;
        } else {
            $swatchImageMap[$sIndex] = isset($images[$sIndex]) ? $sIndex : 0;
        }
    }

    // Tentukan foto yang aktif saat halaman dimuat (utamakan is_primary):
    $primaryImgIndex = $images->search(fn ($img) => (bool) $img->is_primary);
    $initialImgIndex = $primaryImgIndex !== false ? $primaryImgIndex : 0;

    // Tentukan swatch yang aktif yang sesuai dengan initialImgIndex:
    $primaryImage = $images[$initialImgIndex] ?? null;
    $initialSwatchIndex = 0;
    if ($primaryImage && $primaryImage->color_name) {
        foreach ($colorSwatches as $sIndex => $swatch) {
            if ($swatch['name'] === $primaryImage->color_name) {
                $initialSwatchIndex = $sIndex;
                break;
            }
        }
    } else {
        foreach ($swatchImageMap as $sIndex => $targetImgIndex) {
            if ($targetImgIndex === $initialImgIndex) {
                $initialSwatchIndex = $sIndex;
                break;
            }
        }
    }

    $initialColorName = $colorSwatches[$initialSwatchIndex]['name'] ?? ($primaryImage?->color_name ?: ($car->color ?: '-'));
    $initialSwatchStock = (int) ($colorSwatches[$initialSwatchIndex]['stock'] ?? $car->stock);
    $creditUrl = Route::has('credit.index') ? route('credit.index', ['mobil' => $car->slug]) : null;
@endphp

@section('title', $title)
@section('meta_description', $metaDescription)

@section('og_type', 'product')
@if ($images->isNotEmpty())
    @section('og_image', $images->first()->url)
@endif

@php
    // Data terstruktur (schema.org) untuk hasil pencarian Google.
    $structuredData = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Car',
        'name' => $title,
        'brand' => ['@type' => 'Brand', 'name' => $car->brand->name],
        'model' => $car->name,
        'vehicleModelDate' => (string) $car->year,
        'itemCondition' => $car->isNew() ? 'https://schema.org/NewCondition' : 'https://schema.org/UsedCondition',
        'description' => $metaDescription,
        'image' => $images->map(fn ($image) => $image->url)->values()->all() ?: null,
        'url' => $detailUrl,
        'offers' => [
            '@type' => 'Offer',
            'price' => $finalPrice,
            'priceCurrency' => 'IDR',
            'availability' => $car->inStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => $detailUrl,
            'seller' => ['@type' => 'AutoDealer', 'name' => config('dealer.name')],
        ],
    ]);
@endphp

@push('meta')
    {{-- JSON-LD tidak dieksekusi browser, jadi aman untuk CSP; JSON_HEX_TAG mencegah teks keluar dari tag script. --}}
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <div class="container py-4">
        @include('partials.public-breadcrumb', ['items' => [
            ['label' => 'Mobil', 'url' => route('cars.index')],
            ['label' => $title],
        ]])

        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                @if ($images->isEmpty())
                    <div class="car-gallery-empty rounded-4" role="img" aria-label="Belum ada foto {{ $title }}">
                        <i class="bi bi-car-front"></i>
                    </div>
                @else
                    <div id="carGallery" class="carousel slide carousel-fade car-gallery" data-bs-interval="false">
                        <div class="carousel-inner rounded-4">
                            @foreach ($images as $image)
                                <div @class(['carousel-item', 'active' => $loop->index === $initialImgIndex]) data-gallery-index="{{ $loop->index }}">
                                    <img src="{{ $image->url }}" alt="{{ $title }}, foto {{ $loop->iteration }} dari {{ $loop->count }}"
                                         class="car-gallery-img" loading="{{ $loop->index === $initialImgIndex ? 'eager' : 'lazy' }}">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-5">
                <div class="car-detail-info">
                    <p class="text-muted mb-1">{{ $car->brand->name }} · {{ $car->category->name }}</p>
                    <h1 class="h2 mb-2">{{ $car->name }} {{ $car->year }}</h1>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span @class(['badge rounded-pill', 'badge-condition-new' => $car->isNew(), 'badge-condition-used border' => ! $car->isNew()])>{{ $car->condition_label }}</span>
                        @if ($car->hasPromoPrice())
                            <span class="badge rounded-pill badge-status-red"><i class="bi bi-percent"></i> Promo</span>
                        @endif
                        @unless ($car->inStock())
                            <x-status-badge status="out_of_stock" />
                        @endunless
                    </div>

                    <div class="mb-2 d-flex align-items-center flex-wrap gap-2" id="carPriceContainer">
                        @if ($car->hasPromoPrice())
                            <del class="price-old d-block w-100"><x-price :amount="$car->price" /></del>
                        @endif
                        <span id="carPriceDisplay" class="price-final car-detail-price" data-base-price="{{ $car->finalPrice() }}"><x-price :amount="$finalPrice" /></span>
                    </div>

                    <p class="mb-3">
                        @if ($car->inStock())
                            <i class="bi bi-box-seam me-1"></i>Stok: <span class="fw-semibold">{{ $car->stock }} unit</span>
                        @else
                            <i class="bi bi-x-circle me-1"></i><span class="fw-semibold">Stok habis</span>
                        @endif
                    </p>

                    @if (!empty($colorSwatches))
                        <div class="car-color-swatches mb-4" id="carColorPicker">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small text-muted fw-semibold">Pilihan Warna:</span>
                                <div class="text-end">
                                    <span class="small fw-bold text-body" id="selectedColorName">{{ $initialColorName }}</span>
                                    <span id="selectedColorStockBadge" @class([
                                        'badge ms-1 small',
                                        'bg-success-subtle text-success border border-success-subtle' => $initialSwatchStock > 0,
                                        'bg-danger-subtle text-danger border border-danger-subtle' => $initialSwatchStock <= 0,
                                    ])>
                                        {{ $initialSwatchStock > 0 ? "Stok: {$initialSwatchStock} unit" : 'Stok Habis' }}
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3" role="radiogroup" aria-label="Pilihan warna mobil">
                                @foreach ($colorSwatches as $index => $swatch)
                                    @php
                                        $targetImgIndex = $swatchImageMap[$index] ?? 0;
                                        $sStock = (int) ($swatch['stock'] ?? 1);
                                    @endphp
                                    <button type="button"
                                            class="color-swatch-btn @if($index === $initialSwatchIndex) active @endif @if($sStock <= 0) is-out-of-stock @endif"
                                            data-color-index="{{ $index }}"
                                            data-image-index="{{ $targetImgIndex }}"
                                            data-color-name="{{ $swatch['name'] }}"
                                            data-color-hex="{{ $swatch['hex'] }}"
                                            data-color-stock="{{ $sStock }}"
                                            data-extra-price="{{ $swatch['extra_price'] ?? 0 }}"
                                            aria-label="{{ $swatch['name'] }} ({{ $sStock > 0 ? 'Stok: '.$sStock : 'Habis' }})"
                                            title="{{ $swatch['name'] }} ({{ $swatch['hex'] }})@if (($swatch['extra_price'] ?? 0) > 0) (+Rp {{ number_format($swatch['extra_price'], 0, ',', '.') }})@endif - {{ $sStock > 0 ? 'Stok: '.$sStock.' unit' : 'Stok Habis' }}"
                                            role="radio"
                                            aria-checked="{{ $index === $initialSwatchIndex ? 'true' : 'false' }}">
                                        <span class="color-swatch-dot" style="background-color: {{ $swatch['hex'] }};">
                                            @if ($sStock <= 0)
                                                <span class="color-swatch-cross">✕</span>
                                            @endif
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($car->activePromos->isNotEmpty())
                        <div class="car-detail-promos mb-4">
                            <p class="small fw-semibold mb-2">Promo berlaku</p>
                            <ul class="list-unstyled small mb-0">
                                @foreach ($car->activePromos as $promo)
                                    <li @class(['d-flex gap-2', 'mb-2' => ! $loop->last])>
                                        <i class="bi bi-tag text-accent" aria-hidden="true"></i>
                                        <span>
                                            <span class="fw-semibold">{{ $promo->title }}</span>
                                            @if ($bestPromo?->is($promo))
                                                <span class="text-accent">(Dipakai di harga)</span>
                                            @endif
                                            <span class="d-block text-muted">
                                                {{ $promo->start_date->translatedFormat('d M Y') }} - {{ $promo->end_date->translatedFormat('d M Y') }}@if ($promo->discount_amount), hemat <x-price :amount="$promo->discount_amount" />@endif
                                            </span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Dua aksi utama berdampingan; favorit, bandingkan, dan WhatsApp di baris kecil di bawahnya. --}}
                    <div class="car-detail-actions">
                        @if ($purchaseRoute)
                            @if ($car->canBePurchased())
                                <a href="{{ route('purchase-requests.create', $car) }}?warna={{ urlencode($initialColorName) }}"
                                   id="btnPurchaseCar"
                                   data-base-url="{{ route('purchase-requests.create', $car) }}"
                                   @class(['btn btn-accent btn-lg', 'disabled' => $initialSwatchStock <= 0])>
                                    <i class="bi bi-cart-check"></i>
                                    <span id="btnPurchaseText">{{ $initialSwatchStock > 0 ? 'Ajukan Pembelian' : 'Warna Ini Habis' }}</span>
                                </a>
                            @else
                                <span class="btn btn-accent btn-lg disabled" aria-disabled="true">
                                    <i class="bi bi-cart-x"></i>Ajukan Pembelian
                                </span>
                            @endif
                        @endif

                        @if ($testDriveRoute && ! auth()->user()?->isStaff())
                            @if ($car->canBeTestDriven())
                                <a href="{{ route('test-drives.create', ['mobil' => $car->slug]) }}" class="btn btn-outline-primary btn-lg">
                                    <i class="bi bi-calendar-check"></i>Booking Test Drive
                                </a>
                            @else
                                <span class="btn btn-outline-primary btn-lg disabled" aria-disabled="true">
                                    <i class="bi bi-calendar-x"></i>Booking Test Drive
                                </span>
                            @endif
                        @endif
                    </div>

                    @if ($purchaseRoute && ! $car->canBePurchased())
                        <p class="small text-muted mt-2 mb-0">Stok habis, pengajuan belum bisa dilakukan.</p>
                    @endif
                    @if ($testDriveRoute)
                        @if (auth()->user()?->isStaff())
                            <p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Booking test drive hanya untuk akun customer.</p>
                        @elseif (! $car->canBeTestDriven())
                            @if ($car->isUsed() && $car->stock === 0)
                                <p class="small text-muted mt-2 mb-0">Unit bekas ini sudah terjual, test drive tidak tersedia.</p>
                            @else
                                <p class="small text-muted mt-2 mb-0">Mobil ini belum tersedia untuk test drive.</p>
                            @endif
                        @endif
                    @endif

                    <div class="d-flex flex-wrap align-items-center gap-3 mt-3 pt-3 border-top car-detail-tools">
                        <x-favorite-button :car="$car" />
                        <x-compare-button :car="$car" />
                        @if ($whatsappUrl)
                            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-link text-body p-0 small">
                                <i class="bi bi-whatsapp me-1"></i>Tanya via WhatsApp
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-2">
            <div class="col-lg-7">
                {{-- Kartu Spesifikasi Lengkap --}}
                <section class="card mb-4" aria-labelledby="spec-title">
                    <div class="card-body p-3 p-lg-4">
                        <h2 id="spec-title" class="h5 mb-3">Spesifikasi</h2>
                        @php
                            $specs = array_filter([
                                'Merek' => $car->brand->name,
                                'Kategori' => $car->category->name,
                                'Kondisi' => $car->condition_label,
                                'Tahun' => $car->year,
                                'Transmisi' => $car->transmission_label,
                                'Bahan bakar' => $car->fuel_type_label,
                                'Kapasitas mesin' => $car->engine_cc ? number_format($car->engine_cc, 0, ',', '.').' cc' : '-',
                                'Jumlah kursi' => "{$car->seats} kursi",
                                'Warna' => $initialColorName,
                                'Kilometer' => $car->isNew() ? null : number_format($car->mileage, 0, ',', '.').' km',
                                'Status Pajak' => $car->tax_status,
                                'Dokumen' => $car->document_status,
                                'Kepemilikan' => $car->ownership_history,
                                'Plat Nomor' => $car->plate_type ? "Plat {$car->plate_type}" : null,
                                'Stok' => $car->inStock() ? "{$car->stock} unit" : 'Habis',
                            ], fn ($value) => filled($value));
                        @endphp
                        <dl class="spec-grid mb-0">
                            @foreach ($specs as $label => $value)
                                <div class="spec-grid-item">
                                    <dt>{{ $label }}</dt>
                                    <dd @if($label === 'Warna') id="specCarColor" @endif>{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </section>

                {{-- Lencana Jaminan & Standar Dealer --}}
                <section class="card mb-4 border-success-subtle bg-success-subtle bg-opacity-10" aria-label="Jaminan Dealer">
                    <div class="card-body p-3 p-lg-4">
                        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                            <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-shield-check me-1"></i>Jaminan Resmi JAF Dealer</span>
                            @if ($car->isUsed())
                                <span class="badge bg-primary rounded-pill px-3 py-2"><i class="bi bi-patch-check me-1"></i>Inspeksi 150+ Titik</span>
                            @else
                                <span class="badge bg-info text-dark rounded-pill px-3 py-2"><i class="bi bi-stars me-1"></i>Unit Baru Bergaransi Resmi</span>
                            @endif
                        </div>
                        <div class="row g-3 small">
                            <div class="col-sm-6 d-flex align-items-start gap-2">
                                <i class="bi bi-check-circle-fill text-success fs-6 mt-1 flex-shrink-0"></i>
                                <div>
                                    <strong class="d-block text-body">Lulus Uji Fisik &amp; Mesin</strong>
                                    <span class="text-muted">Mesin, transmisi, kelistrikan, &amp; rangka teruji aman.</span>
                                </div>
                            </div>
                            <div class="col-sm-6 d-flex align-items-start gap-2">
                                <i class="bi bi-check-circle-fill text-success fs-6 mt-1 flex-shrink-0"></i>
                                <div>
                                    <strong class="d-block text-body">Bebas Banjir &amp; Bebas Tabrakan</strong>
                                    <span class="text-muted">Jaminan unit bukan eks-banjir atau kecelakaan besar.</span>
                                </div>
                            </div>
                            <div class="col-sm-6 d-flex align-items-start gap-2">
                                <i class="bi bi-check-circle-fill text-success fs-6 mt-1 flex-shrink-0"></i>
                                <div>
                                    <strong class="d-block text-body">Garansi Dealer</strong>
                                    <span class="text-muted">{{ $car->warranty_info ?: 'Perlindungan mesin & transmisi siap pakai.' }}</span>
                                </div>
                            </div>
                            <div class="col-sm-6 d-flex align-items-start gap-2">
                                <i class="bi bi-check-circle-fill text-success fs-6 mt-1 flex-shrink-0"></i>
                                <div>
                                    <strong class="d-block text-body">Legalitas Surat Terjamin</strong>
                                    <span class="text-muted">{{ $car->document_status ?: 'Keaslian BPKB, STNK, & faktur 100% tervalidasi.' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                @if ($description !== '')
                    <section class="card mb-4" aria-labelledby="description-title">
                        <div class="card-body p-3 p-lg-4">
                            <h2 id="description-title" class="h5 mb-3">Deskripsi</h2>
                            {{-- Di-escape dulu, baru baris baru diubah jadi <br>: tidak ada HTML mentah. --}}
                            <p class="mb-0 car-description">{!! nl2br(e($description)) !!}</p>
                        </div>
                    </section>
                @endif
            </div>

            <div class="col-lg-5">
                <section class="card" aria-labelledby="credit-title">
                    <div class="card-body p-3 p-lg-4">
                        <h2 id="credit-title" class="h5 mb-2">Ringkasan Cicilan</h2>
                        <p class="small text-muted mb-3" id="creditSummaryHeading">
                            Harga <span id="creditSummaryPrice"><x-price :amount="$finalPrice" /></span>, DP minimum {{ config('credit.dp_min') }}%
                            <span id="creditSummaryMinDp"><x-price :amount="$minDownPayment" /></span>.
                        </p>
                        <table class="table table-sm credit-table mb-2" id="creditSummaryTable" data-dp-min="{{ config('credit.dp_min') }}" data-rounding="{{ config('credit.rounding') }}">
                            <thead>
                                <tr>
                                    <th scope="col">Tenor</th>
                                    <th scope="col">Bunga flat/thn</th>
                                    <th scope="col" class="text-end">Cicilan/bln</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($installments as $installment)
                                    <tr data-tenor="{{ $installment['tenor_months'] }}" data-rate="{{ $installment['interest_rate'] }}">
                                        <td>{{ $installment['tenor_months'] }} bln</td>
                                        <td>{{ str_replace('.', ',', (string) $installment['interest_rate']) }}%</td>
                                        <td class="text-end fw-semibold credit-installment-cell"><x-price :amount="$installment['monthly_installment']" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="small text-muted mb-0">Perkiraan dengan bunga flat; angka final mengikuti persetujuan leasing.</p>
                        @if ($creditUrl)
                            <a href="{{ $creditUrl }}" class="btn btn-outline-primary btn-sm mt-3" id="btnCreditSimulation" data-base-url="{{ route('credit.index', ['mobil' => $car->slug]) }}">
                                <i class="bi bi-calculator"></i>Hitung simulasi sendiri
                            </a>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        @if ($similarCars->isNotEmpty())
            <section class="mt-5" aria-labelledby="similar-title">
                <h2 id="similar-title" class="h4 mb-3">Mobil Serupa</h2>
                <div class="row g-4">
                    @foreach ($similarCars as $similarCar)
                        <div class="col-12 col-sm-6 col-lg-3">
                            <x-car-card :car="$similarCar" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
