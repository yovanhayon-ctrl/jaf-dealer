@extends('layouts.app')

@section('title', 'Booking Test Drive')
@section('meta_description', 'Booking jadwal test drive mobil di '.config('dealer.name').', pilih tanggal dan jam yang Anda inginkan.')

@section('content')
    <div class="container py-4">
        @include('partials.public-breadcrumb', ['items' => [['label' => 'Test Drive']]])

        <div class="mb-4">
            <h1 class="h3 mb-1">Booking Test Drive</h1>
            <p class="text-muted mb-0">
                Pilih mobil dan jadwal, lalu tim kami akan menghubungi Anda lewat WhatsApp untuk konfirmasi.
            </p>
        </div>

        @if ($isAdmin)
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <div>
                            {{ \App\Http\Requests\TestDriveRequest::ADMIN_MESSAGE }}
                            @if (auth()->user()->isAdmin())<a href="{{ route('admin.test-drives.index') }}" class="alert-link">Kelola test drive di dashboard admin</a>.@endif
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($cars->isEmpty())
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <x-empty-state icon="bi-car-front" title="Belum ada mobil yang bisa di-test drive"
                                       message="Silakan cek kembali nanti atau hubungi dealer.">
                            <a href="{{ route('cars.index') }}" class="btn btn-primary btn-sm">Lihat Mobil</a>
                        </x-empty-state>
                    </div>
                </div>
            </div>
        @else
            @if ($unavailableCar)
                <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <div>
                        Mobil {{ $unavailableCar->brand->name }} {{ $unavailableCar->name }} {{ $unavailableCar->year }}
                        tidak tersedia untuk test drive. Silakan pilih mobil lain.
                    </div>
                </div>
            @endif

            <div class="row g-4">
                {{-- Kolom Kiri: Form Booking (col-lg-7) --}}
                <div class="col-lg-7">
                    <div class="card shadow-sm">
                        <div class="card-body p-3 p-lg-4">
                            {{-- Nama Pemesan (Info Akun Login) --}}
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted mb-1">Nama Pemesan</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-body-tertiary text-muted border-end-0">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input type="text" class="form-control bg-body-tertiary border-start-0" value="{{ auth()->user()->name }}" readonly>
                                </div>
                                <div class="form-text small">Sesuai dengan akun login Anda.</div>
                            </div>

                            <form method="POST" action="{{ route('test-drives.store') }}" data-disable-on-submit>
                                @csrf

                                <x-form.select name="car_id" id="car_id" label="Mobil" required placeholder="Pilih mobil"
                                               :value="$selectedCar?->id"
                                               :options="$cars->mapWithKeys(fn ($car) => [$car->id => sprintf('%s %s %s (%s)', $car->brand->name, $car->name, $car->year, $car->condition_label)])->all()"
                                               data-test-drive-cars="{{ json_encode($carsData) }}" />

                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <x-form.input name="preferred_date" type="date" label="Tanggal" required
                                                      min="{{ $firstDate }}" max="{{ $lastDate }}"
                                                      help="Besok s/d 30 hari ke depan." />
                                    </div>
                                    <div class="col-sm-6">
                                        <x-form.select name="preferred_time" label="Jam (WIB)" required placeholder="Pilih jam"
                                                       :options="collect(\App\Models\TestDrive::TIME_SLOTS)->mapWithKeys(fn ($time) => [$time => $time.' WIB'])->all()" />
                                    </div>
                                </div>

                                <x-form.input name="phone" data-number="phone" type="tel" label="Nomor WhatsApp" required
                                              :value="auth()->user()->phone" inputmode="tel" autocomplete="tel"
                                              help="Format 08xx. Dipakai tim kami untuk konfirmasi jadwal." />

                                <x-form.textarea name="notes" label="Catatan (opsional)" rows="3"
                                                 maxlength="{{ \App\Http\Requests\TestDriveRequest::MAX_NOTES_LENGTH }}"
                                                 help="Mis. ingin mencoba rute tol, atau datang bersama keluarga." />

                                {{-- Checkbox Kepemilikan SIM A --}}
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input @error('has_sim') is-invalid @enderror"
                                               type="checkbox" name="has_sim" id="has_sim" value="1" required
                                               {{ old('has_sim') ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="has_sim">
                                            Saya menyatakan telah memiliki SIM A aktif yang masih berlaku dan bersedia menunjukkan SIM A beserta KTP asli saat pelaksanaan Test Drive. <span class="text-danger">*</span>
                                        </label>
                                        @error('has_sim')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="bi bi-calendar-check"></i>Kirim Booking
                                    </button>
                                    <a href="{{ route('account.test-drives.index') }}" class="btn btn-outline-secondary">Riwayat Test Drive</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <ul class="small text-muted mt-3 mb-0">
                        <li>Jadwal dapat dipilih mulai besok sampai 30 hari ke depan, pukul 09.00-16.00 WIB.</li>
                        <li>Status booking "Menunggu" sampai dikonfirmasi dealer; Anda bisa membatalkannya selama belum dikonfirmasi.</li>
                    </ul>
                </div>

                {{-- Kolom Kanan: Pratinjau Mobil & Ketentuan SOP (col-lg-5) --}}
                <div class="col-lg-5">
                    {{-- 1. Kartu Pratinjau Mobil Pilihan --}}
                    <div class="card shadow-sm mb-4" id="testDrivePreviewCard">
                        <div class="card-header bg-transparent py-3 border-bottom d-flex align-items-center">
                            <i class="bi bi-car-front text-accent fs-5 me-2"></i>
                            <h2 class="h6 mb-0 fw-bold">Pratinjau Mobil Pilihan</h2>
                        </div>
                        <div class="card-body p-3 p-lg-4">
                            {{-- Kontainer Mobil Terpilih --}}
                            <div id="testDrivePreviewSelected" class="{{ $selectedCar ? '' : 'd-none' }}">
                                <div class="position-relative mb-3 overflow-hidden rounded-3 border bg-body-tertiary" style="aspect-ratio: 16 / 9;">
                                    <img id="previewCarImg"
                                         src="{{ $selectedCar?->primaryImage?->thumb_url ?? '' }}"
                                         alt="{{ $selectedCar ? $selectedCar->brand->name.' '.$selectedCar->name.' '.$selectedCar->year : 'Foto Mobil' }}"
                                         class="w-100 h-100 object-fit-cover {{ $selectedCar?->primaryImage ? '' : 'd-none' }}">
                                    <div id="previewCarImgEmpty" class="w-100 h-100 d-flex align-items-center justify-content-center text-muted {{ $selectedCar?->primaryImage ? 'd-none' : '' }}">
                                        <i class="bi bi-car-front display-4"></i>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                    <span id="previewCarCondition" class="badge {{ $selectedCar?->isNew() ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border' }}">
                                        {{ $selectedCar?->condition_label ?? '' }}
                                    </span>
                                    <span id="previewCarCategory" class="badge bg-body-tertiary text-body border">
                                        {{ $selectedCar?->category?->name ?? 'Mobil' }}
                                    </span>
                                    <span id="previewCarStock" class="badge bg-body-tertiary text-muted border">
                                        {{ $selectedCar ? ($selectedCar->inStock() ? "Stok: {$selectedCar->stock} unit" : 'Unit Display (Stok 0)') : '' }}
                                    </span>
                                </div>

                                <h3 id="previewCarName" class="h5 fw-bold font-heading mb-1">
                                    {{ $selectedCar ? $selectedCar->brand->name.' '.$selectedCar->name.' '.$selectedCar->year : '' }}
                                </h3>

                                <div class="row g-2 py-2 border-top border-bottom my-3 small text-muted">
                                    <div class="col-6 d-flex align-items-center gap-2">
                                        <i class="bi bi-gear text-accent"></i>
                                        <span id="previewCarTransmission">{{ $selectedCar?->transmission_label ?? '-' }}</span>
                                    </div>
                                    <div class="col-6 d-flex align-items-center gap-2">
                                        <i class="bi bi-fuel-pump text-accent"></i>
                                        <span id="previewCarFuel">{{ $selectedCar?->fuel_type_label ?? '-' }}</span>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-baseline mb-3">
                                    <span class="small text-muted">Harga OTR</span>
                                    <span id="previewCarPrice" class="fs-5 fw-bold text-accent">
                                        {{ $selectedCar ? 'Rp '.number_format((int) $selectedCar->finalPrice(), 0, ',', '.') : '-' }}
                                    </span>
                                </div>

                                <a id="previewCarDetailLink"
                                   href="{{ $selectedCar ? route('cars.show', $selectedCar) : '#' }}"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-secondary w-100">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Lihat detail mobil
                                </a>
                            </div>

                            {{-- Placeholder Kosong --}}
                            <div id="testDrivePreviewPlaceholder" class="text-center py-4 {{ $selectedCar ? 'd-none' : '' }}">
                                <div class="mb-3">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary p-3 border text-muted">
                                        <i class="bi bi-car-front display-6"></i>
                                    </span>
                                </div>
                                <p class="text-muted small mb-0 px-2">
                                    Silakan pilih unit mobil di samping untuk melihat ringkasan spesifikasi.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Kartu Ketentuan & Lokasi Test Drive --}}
                    <div class="card shadow-sm">
                        <div class="card-header bg-transparent py-3 border-bottom d-flex align-items-center">
                            <i class="bi bi-shield-check text-accent fs-5 me-2"></i>
                            <h2 class="h6 mb-0 fw-bold">Ketentuan & Lokasi Test Drive</h2>
                        </div>
                        <div class="card-body p-3 p-lg-4">
                            <ul class="list-unstyled mb-3 small d-flex flex-column gap-3">
                                <li class="d-flex align-items-start gap-2">
                                    <span class="badge rounded-circle bg-accent-subtle text-accent p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">1</span>
                                    <div>
                                        <span class="fw-semibold">Wajib Dokumen Asli:</span>
                                        <span class="text-muted d-block">Membawa SIM A aktif yang masih berlaku dan KTP asli saat kedatangan.</span>
                                    </div>
                                </li>
                                <li class="d-flex align-items-start gap-2">
                                    <span class="badge rounded-circle bg-accent-subtle text-accent p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">2</span>
                                    <div>
                                        <span class="fw-semibold">Waktu Kedatangan:</span>
                                        <span class="text-muted d-block">Hadir di Showroom JAF Dealer 15 menit sebelum sesi dimulai.</span>
                                    </div>
                                </li>
                                <li class="d-flex align-items-start gap-2">
                                    <span class="badge rounded-circle bg-accent-subtle text-accent p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">3</span>
                                    <div>
                                        <span class="fw-semibold">Durasi & Pendampingan:</span>
                                        <span class="text-muted d-block">Sesi uji kendara berdurasi sekitar 30–45 menit didampingi oleh staf Sales resmi JAF Dealer.</span>
                                    </div>
                                </li>
                                <li class="d-flex align-items-start gap-2">
                                    <span class="badge rounded-circle bg-accent-subtle text-accent p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">4</span>
                                    <div>
                                        <span class="fw-semibold">Jam Operasional:</span>
                                        <span class="text-muted d-block">Jadwal sesi Test Drive tersedia pukul 09.00 – 16.00 WIB.</span>
                                    </div>
                                </li>
                            </ul>

                            <div class="p-3 rounded-3 bg-body-tertiary border small">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-geo-alt-fill text-accent"></i>
                                    <span class="fw-semibold font-heading">Lokasi Showroom JAF Dealer</span>
                                </div>
                                <p class="text-muted mb-0">
                                    {{ config('dealer.address') ?: 'Showroom Utama JAF Dealer' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ \App\Support\Asset::url('js/test-drive.js') }}"></script>
@endpush
