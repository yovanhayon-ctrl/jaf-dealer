@props(['filters', 'categories', 'carModels' => [], 'idPrefix' => ''])

@php
    $id = fn (string $name) => "{$idPrefix}filter_{$name}";
    $digits = fn (?int $amount) => $amount === null ? '' : number_format($amount, 0, ',', '.');
@endphp

<form method="GET" action="{{ route('spare-parts.index') }}" class="filter-form" role="search" aria-label="Filter suku cadang">
    @if (($filters['urut'] ?? 'terbaru') !== 'terbaru')
        <input type="hidden" name="urut" value="{{ $filters['urut'] }}">
    @endif

    <div class="mb-3">
        <label for="{{ $id('q') }}" class="form-label small fw-semibold mb-1">Kata kunci</label>
        <input type="search" id="{{ $id('q') }}" name="q" value="{{ $filters['q'] ?? '' }}"
               class="form-control form-control-sm" placeholder="Nama part atau nomor part…">
    </div>

    <div class="mb-3">
        <label for="{{ $id('kategori') }}" class="form-label small fw-semibold mb-1">Kategori</label>
        <select id="{{ $id('kategori') }}" name="kategori" class="form-select form-select-sm">
            <option value="">Semua Kategori</option>
            @foreach ($categories as $val => $label)
                <option value="{{ $val }}" @selected(($filters['kategori'] ?? '') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label for="{{ $id('model') }}" class="form-label small fw-semibold mb-1">Kompatibilitas Mobil</label>
        <select id="{{ $id('model') }}" name="model" class="form-select form-select-sm">
            <option value="">Semua Mobil</option>
            @foreach ($carModels as $val => $info)
                <option value="{{ $val }}" @selected(($filters['model'] ?? '') === $val)>{{ $info['label'] }}</option>
            @endforeach
        </select>
    </div>

    <fieldset class="mb-3">
        <legend class="form-label small fw-semibold mb-1">Harga (Rp)</legend>
        <div class="row g-2">
            <div class="col-6">
                <label for="{{ $id('harga_min') }}" class="visually-hidden">Harga minimum</label>
                <input type="text" inputmode="numeric" id="{{ $id('harga_min') }}" name="harga_min" data-number="thousands"
                       value="{{ $digits($filters['harga_min'] ?? null) }}"
                       class="form-control form-control-sm" placeholder="Min">
            </div>
            <div class="col-6">
                <label for="{{ $id('harga_max') }}" class="visually-hidden">Harga maksimum</label>
                <input type="text" inputmode="numeric" id="{{ $id('harga_max') }}" name="harga_max" data-number="thousands"
                       value="{{ $digits($filters['harga_max'] ?? null) }}"
                       class="form-control form-control-sm" placeholder="Maks">
            </div>
        </div>
    </fieldset>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="{{ $id('tersedia') }}" name="tersedia" value="1"
               @checked(! empty($filters['tersedia']))>
        <label class="form-check-label small" for="{{ $id('tersedia') }}">Hanya stok tersedia</label>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel"></i>Terapkan</button>
        <a href="{{ route('spare-parts.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
</form>
