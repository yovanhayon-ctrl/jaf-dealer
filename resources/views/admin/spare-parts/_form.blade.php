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

<div class="mb-3">
    <label for="image" class="form-label">Foto Produk <span class="text-muted small">(opsional)</span></label>

    <img id="imagePreview" src="{{ $sparePart->imageUrl() }}" alt="Pratinjau foto spare part"
         @class(['d-block mb-2 rounded border p-1', 'd-none' => ! $sparePart->image]) style="max-height: 160px; object-fit: contain;">

    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
           data-preview-target="#imagePreview"
           @class(['form-control', 'is-invalid' => $errors->has('image')])>
    @error('image')
        <div class="invalid-feedback">{{ $message }}</div>
    @else
        <div class="form-text">Format: JPG, JPEG, PNG, atau WEBP. Maksimal 2 MB. Otomatis dioptimalkan sebagai WebP.</div>
    @enderror

    @if ($sparePart->image)
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" value="1" id="remove_image" name="remove_image" @checked(old('remove_image'))>
            <label class="form-check-label text-danger small" for="remove_image">Hapus foto saat ini</label>
        </div>
    @endif
</div>

<x-form.checkbox name="is_active" label="Aktif" :checked="$sparePart->is_active ?? true"
                 help="Spare part aktif dapat dilihat oleh mekanik dan dicatat pada riwayat servis." />

<div class="admin-form-actions">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg"></i>Simpan
    </button>
    <a href="{{ route('admin.spare-parts.index') }}" class="btn btn-outline-secondary">Batal</a>
</div>
