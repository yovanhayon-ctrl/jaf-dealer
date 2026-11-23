@csrf

@php
    $formatNumber = fn ($value) => filled($value) ? number_format((int) $value, 0, ',', '.') : '';
    $categoryOptions = \App\Models\SparePart::CATEGORIES;
    $unitOptions = \App\Models\SparePart::UNITS;
@endphp

<div class="row gx-3">
    <div class="col-md-6">
        <x-form.input name="part_number" label="Kode Part / SKU" :value="$sparePart->part_number" required maxlength="50" autofocus
                      placeholder="Contoh: 15208-65F0A" help="Kode unik spare part pabrikan atau nomor stok gudang." />
    </div>
    <div class="col-md-6">
        <x-form.input name="name" label="Nama Spare Part" :value="$sparePart->name" required maxlength="150"
                      placeholder="Contoh: Filter Oli Mesin Genuine" :help="$sparePart->exists ? 'Slug URL: '.$sparePart->slug : 'Slug URL dibuat otomatis.'" />
    </div>

    <div class="col-md-6">
        <x-form.select name="category" label="Kategori" :options="$categoryOptions" :value="$sparePart->category" required
                       placeholder="Pilih kategori" />
    </div>
    <div class="col-md-6">
        <x-form.select name="unit" label="Satuan" :options="$unitOptions" :value="$sparePart->unit ?: 'pcs'" required />
    </div>

    <div class="col-md-6">
        <x-form.input name="price" data-number="thousands" label="Harga Jual (Rp)" :value="$formatNumber($sparePart->price)"
                      inputmode="numeric" placeholder="Contoh: 85.000" required help="Harga per satuan barang." />
    </div>
    <div class="col-md-3 col-6">
        <x-form.input name="stock" data-number="digits" label="Stok Fisik" :value="$sparePart->stock ?? 0"
                      inputmode="numeric" required help="Jumlah barang tersedia di bengkel." />
    </div>
    <div class="col-md-3 col-6">
        <x-form.input name="min_stock" data-number="digits" label="Batas Minimum Stok" :value="$sparePart->min_stock ?? 5"
                      inputmode="numeric" help="Peringatan restock jika stok ≤ angka ini." />
    </div>

    <div class="col-12">
        <x-form.input name="compatibility" label="Kecocokan Model Mobil" :value="$sparePart->compatibility" maxlength="255"
                      placeholder="Contoh: Nissan Grand Livina (2007-2018), March, Juke, Serena C26"
                      help="Tulis tipe atau tahun mobil yang cocok (opsional)." />
    </div>

    <div class="col-12">
        <x-form.textarea name="description" label="Deskripsi / Catatan" :value="$sparePart->description" rows="3" maxlength="2000"
                         placeholder="Contoh: Suku cadang original Nissan Genuine Parts. Disarankan ganti setiap 10.000 km." />
    </div>
</div>

<div class="card border rounded-3 mb-4 bg-body-tertiary">
    <div class="card-body p-3 p-md-4">
        <label class="form-label fw-semibold mb-1">
            <i class="bi bi-image me-1 text-primary"></i> Foto Spare Part <span class="text-muted fw-normal small">(opsional)</span>
        </label>
        <p class="text-muted small mb-3">
            Bisa menggunakan link gambar dari internet (Google / supplier) atau unggah file gambar langsung dari perangkat.
        </p>

        <div class="row g-3 align-items-start">
            {{-- Kotak Pratinjau Foto --}}
            <div class="col-12 col-sm-auto text-center">
                <div class="border rounded-3 bg-white p-2 d-flex flex-column align-items-center justify-content-center text-center mx-auto"
                     style="width: 140px; height: 140px; min-width: 140px; overflow: hidden; position: relative;">
                    <img id="sparePartImagePreview"
                         src="{{ $sparePart->imageUrl() ?: '' }}"
                         alt="Pratinjau foto spare part"
                         referrerpolicy="no-referrer"
                         @class(['img-fluid h-100 w-100', 'd-none' => ! $sparePart->image])
                         style="object-fit: contain;">

                    <div id="sparePartEmptyPreview" @class(['text-muted py-2', 'd-none' => (bool) $sparePart->image])>
                        <i class="bi bi-box-seam fs-1 d-block opacity-50"></i>
                        <span class="small text-nowrap" style="font-size: 0.72rem;">Belum ada foto</span>
                    </div>

                    <div id="sparePartImageError" class="d-none text-danger py-2">
                        <i class="bi bi-exclamation-triangle fs-2 d-block"></i>
                        <span class="small" style="font-size: 0.7rem; line-height: 1.1;">Link gambar tidak dapat dimuat</span>
                    </div>
                </div>

                @if ($sparePart->image)
                    <div class="text-center mt-2">
                        @if ($sparePart->isExternalImage())
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle small" style="font-size: 0.7rem;">
                                <i class="bi bi-link-45deg"></i> Link Eksternal
                            </span>
                        @else
                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle small" style="font-size: 0.7rem;">
                                <i class="bi bi-hdd"></i> File Terunggah
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Kolom Input Foto --}}
            <div class="col">
                {{-- Opsi 1: Link URL Gambar --}}
                <div class="mb-3">
                    <label for="image_url" class="form-label small fw-semibold text-secondary mb-1">
                        <i class="bi bi-link-45deg me-1"></i> Pilihan A: Tempel Link URL Gambar (Google / Web Eksternal)
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-globe"></i></span>
                        <input type="url" id="image_url" name="image_url"
                               class="form-control @error('image_url') is-invalid @enderror"
                               placeholder="https://contoh.com/gambar-sparepart.jpg atau link foto dari Google"
                               value="{{ old('image_url', $sparePart->isExternalImage() ? $sparePart->image : '') }}">
                        <button type="button" class="btn btn-outline-secondary" id="btnPreviewUrl">
                            <i class="bi bi-eye me-1"></i>Cek Link
                        </button>
                    </div>
                    @error('image_url')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @else
                        <div class="form-text" style="font-size: 0.75rem;">
                            Salin tautan gambar langsung (URL berawalan <code>http://</code> atau <code>https://</code>).
                        </div>
                    @enderror

                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" value="1" id="save_to_storage" name="save_to_storage"
                               @checked(old('save_to_storage', true))>
                        <label class="form-check-label small text-muted" for="save_to_storage">
                            <i class="bi bi-cloud-arrow-down me-1"></i>Unduh & simpan otomatis ke server (disarankan agar foto tidak rusak jika link eksternal mati)
                        </label>
                    </div>
                </div>

                <div class="d-flex align-items-center my-2">
                    <hr class="flex-grow-1 my-0 text-muted opacity-25">
                    <span class="badge text-bg-light border text-muted px-2 py-1 mx-2" style="font-size: 0.7rem;">ATAU</span>
                    <hr class="flex-grow-1 my-0 text-muted opacity-25">
                </div>

                {{-- Opsi 2: Upload File --}}
                <div class="mb-2">
                    <label for="image" class="form-label small fw-semibold text-secondary mb-1">
                        <i class="bi bi-upload me-1"></i> Pilihan B: Unggah File Gambar dari Komputer / HP
                    </label>
                    <input type="file" id="image" name="image"
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                           class="form-control form-control-sm @error('image') is-invalid @enderror">
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @else
                        <div class="form-text" style="font-size: 0.75rem;">
                            Format: JPG, JPEG, PNG, atau WEBP. Maksimal 2 MB (otomatis dioptimalkan).
                        </div>
                    @enderror
                </div>

                @if ($sparePart->image)
                    <div class="form-check mt-3 pt-2 border-top">
                        <input class="form-check-input" type="checkbox" value="1" id="remove_image" name="remove_image" @checked(old('remove_image'))>
                        <label class="form-check-label text-danger small fw-semibold" for="remove_image">
                            <i class="bi bi-trash3 me-1"></i> Hapus foto saat ini
                        </label>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<x-form.checkbox name="is_active" label="Aktif" :checked="$sparePart->is_active ?? true"
                 help="Spare part aktif dapat dilihat oleh mekanik dan dicatat pada riwayat servis." />

<div class="admin-form-actions">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg"></i>Simpan
    </button>
    <a href="{{ route('admin.spare-parts.index') }}" class="btn btn-outline-secondary">Batal</a>
</div>
