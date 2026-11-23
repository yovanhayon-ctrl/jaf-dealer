@extends('layouts.admin')

@section('title', 'Galeri Mobil')

@section('breadcrumb')
    @include('partials.breadcrumb', ['items' => [
        ['label' => 'Mobil', 'url' => route('admin.cars.index')],
        ['label' => $car->name.' '.$car->year, 'url' => route('admin.cars.edit', $car)],
        ['label' => 'Galeri'],
    ]])
@endsection

@php
    $altText = $car->name.' '.$car->year;
    $isFull = $car->images->count() >= $max;
    $uploadErrors = collect($errors->get('images'))->merge(collect($errors->get('images.*'))->flatten())->unique();
@endphp

@section('content')
    <x-admin.detail-header :title="$altText" :subtitle="$car->images->count().' dari '.$max.' gambar'"
                           :back-url="route('admin.cars.index')" back-label="Mobil">
        <a href="{{ route('admin.cars.edit', $car) }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil-square"></i>Edit Mobil
        </a>
        @if ($car->is_active && Route::has('cars.show'))
            <a href="{{ route('cars.show', $car) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-box-arrow-up-right"></i>Lihat di Katalog
            </a>
        @endif
    </x-admin.detail-header>

    <div class="card mb-3">
        <div class="card-body p-3 p-md-4">
            <form method="POST" action="{{ route('admin.cars.images.store', $car) }}" enctype="multipart/form-data" novalidate>
                @csrf

                <label for="images" class="form-label">Unggah Gambar</label>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <input type="file" id="images" name="images[]" multiple
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                           @disabled($isFull)
                           @class(['form-control', 'is-invalid' => $uploadErrors->isNotEmpty()])>
                    <button type="submit" class="btn btn-primary text-nowrap" @disabled($isFull)>
                        <i class="bi bi-upload"></i>Unggah
                    </button>
                </div>

                @if ($uploadErrors->isNotEmpty())
                    <div class="invalid-feedback d-block">
                        @foreach ($uploadErrors as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="form-text">
                    @if ($isFull)
                        Galeri sudah penuh. Hapus gambar lain untuk mengunggah yang baru.
                    @else
                        JPG, JPEG, PNG, atau WEBP · maks. {{ intdiv(\App\Http\Requests\Admin\CarImageRequest::MAX_KILOBYTES, 1024) }} MB per file · minimal 600×400 piksel ·
                        bisa pilih beberapa file (sisa {{ $max - $car->images->count() }} slot).
                        Gambar pertama otomatis menjadi gambar utama.
                    @endif
                </div>
            </form>
        </div>
    </div>

    @php
        $swatches = $car->colorSwatches();
    @endphp

    <div class="card mb-3">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-palette-fill text-primary"></i>
                    <span class="fw-semibold small">Daftar Pilihan Warna & Stok ({{ count($swatches) }} Varian · Total {{ $car->stock }} Unit)</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#collapseStockColor" aria-expanded="false" aria-controls="collapseStockColor">
                        <i class="bi bi-box-seam me-1"></i>Ubah Stok Warna
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#collapseAddColor" aria-expanded="false" aria-controls="collapseAddColor">
                        <i class="bi bi-plus-lg me-1"></i>Tambah Warna
                    </button>
                </div>
            </div>

            {{-- Form Tambah Warna (Collapse) --}}
            <div class="collapse mb-3" id="collapseAddColor">
                <div class="p-3 rounded-3 border bg-body-tertiary">
                    <form method="POST" action="{{ route('admin.cars.images.colors.store', $car) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label for="new_color_name" class="form-label small fw-semibold mb-1">Nama Warna</label>
                            <input type="text" id="new_color_name" name="name" class="form-control form-control-sm" placeholder="Misal: Kuning Cerah (Solar Yellow)" required>
                        </div>
                        <div class="col-md-3">
                            <label for="new_color_hex" class="form-label small fw-semibold mb-1">Kode Warna (HEX)</label>
                            <div class="input-group input-group-sm">
                                <input type="color" id="new_color_picker" class="form-control form-control-color form-control-sm p-1" value="#EAB308" title="Pilih visual warna" onchange="document.getElementById('new_color_hex').value = this.value">
                                <input type="text" id="new_color_hex" name="hex" class="form-control font-monospace" placeholder="#EAB308" value="#EAB308" required pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label for="new_color_stock" class="form-label small fw-semibold mb-1">Stok Unit</label>
                            <input type="number" id="new_color_stock" name="stock" class="form-control form-control-sm" value="1" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="new_color_extra_price" class="form-label small fw-semibold mb-1">Biaya Tambahan Cat (Rp)</label>
                            <input type="text" inputmode="numeric" data-number="thousands" id="new_color_extra_price" name="extra_price" class="form-control form-control-sm" value="0" placeholder="0">
                        </div>
                        <div class="col-12 text-end mt-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle me-1"></i> Simpan Warna Baru
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Form Kelola Stok & Harga Per Warna (Collapse) --}}
            <div class="collapse mb-3" id="collapseStockColor">
                <div class="p-3 rounded-3 border bg-body-tertiary">
                    <form method="POST" action="{{ route('admin.cars.images.colors.stock', $car) }}">
                        @csrf
                        @method('PATCH')
                        <p class="small fw-semibold mb-2"><i class="bi bi-box-seam me-1"></i>Sesuaikan Stok & Biaya Tambahan Cat Masing-Masing Warna:</p>
                        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-2 mb-3">
                            @foreach ($swatches as $sIdx => $s)
                                <div class="col border rounded-3 p-2 bg-body">
                                    <label class="form-label small d-flex align-items-center gap-1 mb-2 fw-semibold">
                                        <span class="rounded-circle d-inline-block flex-shrink-0" style="width: 12px; height: 12px; background-color: {{ $s['hex'] }}; border: 1px solid rgba(0,0,0,0.3);"></span>
                                        <span class="text-truncate" title="{{ $s['name'] }}">{{ $s['name'] }}</span>
                                    </label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <span class="text-muted small" style="font-size: 0.72rem;">Stok:</span>
                                            <div class="input-group input-group-sm">
                                                <input type="number" name="stocks[{{ $sIdx }}]" class="form-control" value="{{ $s['stock'] ?? 1 }}" min="0" required>
                                                <span class="input-group-text">unit</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted small" style="font-size: 0.72rem;">Biaya Cat (Rp):</span>
                                            <input type="text" inputmode="numeric" data-number="thousands" name="extra_prices[{{ $sIdx }}]" class="form-control form-control-sm" value="{{ !empty($s['extra_price']) ? number_format($s['extra_price'], 0, ',', '.') : '0' }}" placeholder="0">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-check2-circle me-1"></i>Simpan Perubahan Stok & Biaya Cat
                        </button>
                    </form>
                </div>
            </div>

            {{-- Badges Warna yang Ada --}}
            <div class="d-flex flex-wrap align-items-center gap-2">
                @foreach ($swatches as $sIdx => $s)
                    <div class="badge rounded-pill d-inline-flex align-items-center gap-1 p-1 px-2 border" 
                         style="background-color: {{ $s['hex'] }}; color: {{ in_array($s['hex'], ['#F4F6F9', '#F8FAFC', '#C0C5CE', '#BFC4CA', '#C8BEAB', '#EAB308', '#FACC15']) ? '#1C1C1E' : '#FFFFFF' }};">
                        <span class="rounded-circle d-inline-block" style="width: 10px; height: 10px; background-color: {{ $s['hex'] }}; border: 1px solid rgba(0,0,0,0.3);"></span>
                        <span class="small">{{ $s['name'] }}</span>
                        <span class="badge bg-dark bg-opacity-25 ms-1" style="font-family: monospace; font-size: 0.65rem;">{{ $s['hex'] }}</span>
                        <span class="badge {{ ($s['stock'] ?? 1) > 0 ? 'bg-success' : 'bg-danger' }} ms-1" style="font-size: 0.65rem;">
                            Stok: {{ $s['stock'] ?? 1 }}
                        </span>
                        @if (($s['extra_price'] ?? 0) > 0)
                            <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">
                                +Rp {{ number_format($s['extra_price'], 0, ',', '.') }}
                            </span>
                        @endif
                        
                        <form method="POST" action="{{ route('admin.cars.images.colors.destroy', [$car, $sIdx]) }}" class="d-inline" data-confirm="Hapus warna '{{ $s['name'] }}' dari mobil ini?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-close ms-1" style="font-size: 0.55rem; filter: {{ in_array($s['hex'], ['#F4F6F9', '#F8FAFC', '#C0C5CE', '#BFC4CA', '#C8BEAB', '#EAB308', '#FACC15']) ? 'none' : 'invert(1)' }};" aria-label="Hapus warna"></button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="small text-muted mt-2">
                <i class="bi bi-info-circle me-1"></i>Klik bulatan warna pada masing-masing foto di bawah untuk menetapkan warna foto tersebut.
            </div>
        </div>
    </div>

    <div class="card">
        @if ($car->images->isEmpty())
            <x-empty-state icon="bi-images" title="Belum ada gambar"
                           message="Unggah foto mobil agar tampil menarik di katalog." />
        @else
            <div class="card-body p-3 p-md-4">
                <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
                    @foreach ($car->images as $image)
                        <div class="col">
                            <div @class(['card h-100 car-image-card', 'border-primary' => $image->is_primary])>
                                <div class="position-relative">
                                    {{-- Badge di luar .ratio karena setiap anak .ratio dibuat memenuhi kotak. --}}
                                    <div class="ratio ratio-16x9">
                                        <img src="{{ $image->thumb_url }}" alt="{{ $altText }}" loading="lazy"
                                             class="object-fit-cover rounded-top">
                                    </div>
                                    @if ($image->is_primary)
                                        <span class="badge bg-primary car-image-badge"><i class="bi bi-star-fill me-1"></i>Utama</span>
                                    @endif
                                </div>
                                <div class="card-body p-2 d-flex flex-column gap-2">
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="small text-muted fw-bold me-auto">#{{ $loop->iteration }}</span>

                                        <form method="POST" action="{{ route('admin.cars.images.move', [$car, $image, 'naik']) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Naikkan urutan"
                                                    aria-label="Naikkan urutan gambar #{{ $loop->iteration }}" @disabled($loop->first)>
                                                <i class="bi bi-arrow-up m-0"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.cars.images.move', [$car, $image, 'turun']) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Turunkan urutan"
                                                    aria-label="Turunkan urutan gambar #{{ $loop->iteration }}" @disabled($loop->last)>
                                                <i class="bi bi-arrow-down m-0"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus gambar"
                                                aria-label="Hapus gambar #{{ $loop->iteration }}"
                                                data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                data-delete-url="{{ route('admin.cars.images.destroy', [$car, $image]) }}"
                                                data-delete-name="#{{ $loop->iteration }}{{ $image->is_primary ? ' (gambar utama)' : '' }}">
                                            <i class="bi bi-trash m-0"></i>
                                        </button>
                                    </div>

                                    {{-- Pemilih Warna Gambar: Tombol Swatches Bulat --}}
                                    <form method="POST" action="{{ route('admin.cars.images.color', [$car, $image]) }}" class="w-100">
                                        @csrf
                                        @method('PATCH')
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="small text-muted fw-semibold" style="font-size: 0.75rem;">Pilih Warna:</span>
                                            @if ($image->color_name)
                                                <button type="submit" name="color" value="" class="btn btn-link btn-sm text-muted p-0 text-decoration-none" style="font-size: 0.7rem;" title="Hapus penetapan warna">
                                                    Reset
                                                </button>
                                            @endif
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mb-2" role="group" aria-label="Pilihan warna foto">
                                            @foreach ($swatches as $swatch)
                                                @php
                                                    $val = $swatch['name'].'|'.$swatch['hex'];
                                                    $isSelected = ($image->color_name === $swatch['name']);
                                                @endphp
                                                <button type="submit" name="color" value="{{ $val }}"
                                                        class="color-swatch-btn @if($isSelected) active @endif"
                                                        style="width: 32px; height: 32px;"
                                                        title="{{ $swatch['name'] }} ({{ $swatch['hex'] }})"
                                                        aria-label="{{ $swatch['name'] }}">
                                                    <span class="color-swatch-dot" style="width: 24px; height: 24px; background-color: {{ $swatch['hex'] }};"></span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </form>

                                    @if ($image->color_name && $image->color_hex)
                                        <div class="d-flex align-items-center gap-2 p-1 px-2 rounded-2 border" style="background-color: var(--dm-tint);">
                                            <span class="rounded-circle d-inline-block flex-shrink-0" style="width: 12px; height: 12px; background-color: {{ $image->color_hex }}; border: 1px solid rgba(0,0,0,0.3);"></span>
                                            <span class="small fw-semibold text-truncate" title="{{ $image->color_name }}" style="font-size: 0.75rem;">{{ $image->color_name }}</span>
                                        </div>
                                    @else
                                        <div class="small text-muted fst-italic" style="font-size: 0.7rem;">
                                            <i class="bi bi-info-circle me-1"></i>Pilih salah satu bulatan warna di atas
                                        </div>
                                    @endif

                                    @unless ($image->is_primary)
                                        <form method="POST" action="{{ route('admin.cars.images.primary', [$car, $image]) }}" class="w-100 mt-auto">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                                <i class="bi bi-star"></i>Jadikan Utama
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <x-delete-modal entity="gambar" />
@endsection
