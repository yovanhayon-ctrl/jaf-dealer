@extends('layouts.app')

@section('title', 'Booking Servis')
@section('meta_description', 'Booking jadwal servis kendaraan di '.config('dealer.name').', pilih layanan, tanggal, dan jam yang Anda inginkan.')

@php
    $slots = \App\Models\ServiceBooking::TIME_SLOTS;
@endphp

@section('content')
    <div class="container py-4">
        @include('partials.public-breadcrumb', ['items' => [
            ['label' => 'Servis', 'url' => route('services.index')],
            ['label' => 'Booking Servis'],
        ]])

        <div class="mb-4">
            <h1 class="h3 mb-1">Booking Servis</h1>
            <p class="text-muted mb-0">
                Isi data kendaraan dan jadwal, lalu tim bengkel kami akan menghubungi Anda lewat WhatsApp untuk konfirmasi.
            </p>
        </div>

        @if ($isAdmin)
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <div>
                            {{ \App\Http\Requests\ServiceBookingRequest::ADMIN_MESSAGE }}
                            @if (auth()->user()->isAdmin())<a href="{{ route('admin.service-bookings.index') }}" class="alert-link">Kelola booking servis di dashboard admin</a>.@endif
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($services->isEmpty())
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <x-empty-state icon="bi-tools" title="Layanan servis belum tersedia"
                                       message="Silakan cek kembali nanti atau hubungi dealer." />
                    </div>
                </div>
            </div>
        @else
            <div class="row g-4">
                {{-- Kolom Kiri: Form Booking (col-lg-7) --}}
                <div class="col-lg-7">
                    <div class="card shadow-sm">
                        <div class="card-body p-3 p-lg-4">
                            <form method="POST" action="{{ route('service-bookings.store') }}" data-disable-on-submit>
                                @csrf

                                {{-- Pilihan Layanan --}}
                                <div class="mb-4">
                                    <label for="service_id" class="form-label">
                                        Layanan <span class="text-danger ms-1" aria-hidden="true">*</span>
                                    </label>
                                    <select id="service_id" name="service_id" class="form-select @error('service_id') is-invalid @enderror" required>
                                        <option value="">Pilih layanan</option>
                                        @foreach ($services as $service)
                                            @php
                                                $isSelected = (string) old('service_id', $selectedService?->id) === (string) $service->id;
                                                $label = $service->price_from
                                                    ? $service->name.' (mulai Rp '.number_format($service->price_from, 0, ',', '.').')'
                                                    : $service->name;
                                            @endphp
                                            <option value="{{ $service->id }}"{{ $isSelected ? ' selected' : '' }}
                                                    data-duration="{{ $service->duration_minutes ?? 60 }}"
                                                    data-price="{{ $service->price_from ?? 0 }}"
                                                    data-description="{{ $service->description }}">
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('service_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Isi Cepat dari Riwayat Kendaraan Customer --}}
                                @if (isset($userVehicles) && $userVehicles->isNotEmpty())
                                    <div class="mb-4 p-3 rounded-3 bg-body-tertiary border">
                                        <label for="userVehicleSelect" class="form-label small fw-semibold text-muted mb-1">
                                            <i class="bi bi-clock-history me-1"></i>Isi Cepat dari Kendaraan Saya
                                        </label>
                                        <select id="userVehicleSelect" class="form-select form-select-sm" data-user-vehicles="{{ json_encode($userVehicles) }}">
                                            <option value="">Pilih dari riwayat kendaraan Anda</option>
                                            @foreach ($userVehicles as $index => $v)
                                                <option value="{{ $index }}">{{ $v['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <div class="form-text small">Memilih kendaraan akan otomatis mengisi merek, model, plat nomor, dan tahun.</div>
                                    </div>
                                @endif

                                <h2 class="h6 fw-semibold mt-4 mb-3">Data kendaraan</h2>
                                <div class="row g-3">
                                    <div class="col-sm-7">
                                        <x-form.input name="vehicle_model" id="vehicle_model" label="Merek & Model" required maxlength="100"
                                                      placeholder="Contoh: Nissan Livina VL" />
                                    </div>
                                    <div class="col-sm-5">
                                        <x-form.input name="plate_number" id="plate_number" data-plate-number label="Plat Nomor" required maxlength="11"
                                                      placeholder="Contoh: B 1234 ABC" autocapitalize="characters" autocomplete="off" />
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <x-form.input name="vehicle_year" id="vehicle_year" data-number="digits" label="Tahun" inputmode="numeric" maxlength="4"
                                                      placeholder="2020" help="Opsional." />
                                    </div>
                                    <div class="col-6 col-sm-8">
                                        <x-form.input name="mileage" id="mileage" data-number="thousands" label="Kilometer Saat Ini" inputmode="numeric"
                                                      placeholder="Contoh: 45.000" help="Opsional, membantu menentukan paket servis." />
                                    </div>
                                </div>

                                <h2 class="h6 fw-semibold mt-4 mb-3">Jadwal</h2>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <x-form.input name="preferred_date" id="preferred_date" type="date" label="Tanggal" required
                                                      min="{{ $firstDate }}" max="{{ $lastDate }}"
                                                      data-today="{{ $todayDate ?? today()->toDateString() }}"
                                                      data-available-today="{{ json_encode($availableTodaySlots ?? []) }}"
                                                      help="{{ ($firstDate ?? '') === ($todayDate ?? today()->toDateString()) ? 'Bisa untuk hari ini (jeda min. 1 jam) s/d 30 hari ke depan.' : 'Operasional hari ini telah ditutup. Paling cepat besok s/d 30 hari ke depan.' }}" />
                                    </div>
                                    <div class="col-sm-6">
                                        <x-form.select name="preferred_time" id="preferred_time" label="Jam (WIB)" required placeholder="Pilih jam"
                                                       :options="collect($slots)->mapWithKeys(fn ($time) => [$time => $time.' WIB'])->all()" />
                                    </div>
                                    @if ($mechanics !== [])
                                        <div class="col-12">
                                            <x-form.select name="mechanic_id" label="Mekanik (opsional)" placeholder="Bebas, dipilihkan bengkel"
                                                           :options="$mechanics"
                                                           :disabled-options="$busyMechanicIds ?? []"
                                                           help="Pilih bila ingin dikerjakan mekanik tertentu. Bengkel bisa menggantinya bila mekanik berhalangan." />
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-3">
                                    <x-form.input name="phone" data-number="phone" type="tel" label="Nomor WhatsApp" required
                                                  :value="auth()->user()->phone" inputmode="tel" autocomplete="tel"
                                                  help="Format 08xx. Dipakai tim kami untuk konfirmasi jadwal." />
                                </div>

                                <x-form.textarea name="complaint" label="Keluhan (opsional)" rows="3"
                                                 :value="$prefilledComplaint ?? null"
                                                 maxlength="{{ \App\Http\Requests\ServiceBookingRequest::MAX_COMPLAINT_LENGTH }}"
                                                 help="Mis. AC kurang dingin, rem berbunyi, atau ingin sekalian ganti aki." />

                                <div class="d-flex flex-wrap gap-2 align-items-center mt-4">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="bi bi-calendar-check"></i>Kirim Booking
                                    </button>
                                    <a href="{{ route('account.service-bookings.index') }}" class="btn btn-outline-secondary">Riwayat Servis</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <ul class="small text-muted mt-3 mb-0">
                        <li>Jadwal dapat dipilih mulai besok sampai 30 hari ke depan, pukul {{ reset($slots) }}-{{ end($slots) }} WIB.</li>
                        <li>Setiap jam hanya menerima {{ \App\Models\ServiceBooking::slotCapacity() }} kendaraan sesuai kapasitas bengkel.</li>
                        <li>Status booking "Menunggu" sampai dikonfirmasi dealer; Anda bisa membatalkannya selama belum dikonfirmasi.</li>
                    </ul>
                </div>

                {{-- Kolom Kanan: Ringkasan Servis & Estimasi Selesai (col-lg-5) --}}
                <div class="col-lg-5">
                    {{-- 1. Kartu Ringkasan Servis & Estimasi --}}
                    <div class="card shadow-sm mb-4" id="servicePreviewCard">
                        <div class="card-header bg-transparent py-3 border-bottom d-flex align-items-center">
                            <i class="bi bi-tools text-accent fs-5 me-2"></i>
                            <h2 class="h6 mb-0 fw-bold">Ringkasan Servis &amp; Estimasi</h2>
                        </div>
                        <div class="card-body p-3 p-lg-4">
                            {{-- Kontainer Layanan Terpilih --}}
                            <div id="servicePreviewSelected" class="{{ $selectedService ? '' : 'd-none' }}">
                                <h3 id="previewServiceName" class="h5 fw-bold font-heading mb-1 text-accent">
                                    {{ $selectedService?->name ?? '' }}
                                </h3>
                                <p id="previewServiceDesc" class="small text-muted mb-3">
                                    {{ $selectedService?->description ?? 'Layanan servis resmi JAF Service.' }}
                                </p>

                                @if (isset($selectedPart) && $selectedPart)
                                    <div class="p-3 rounded-3 bg-body-tertiary border mb-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="badge rounded-pill badge-condition-new">
                                                <i class="bi bi-box-seam me-1"></i>Suku Cadang Dipesan
                                            </span>
                                            <span class="small font-monospace text-muted">SKU: {{ $selectedPart->part_number }}</span>
                                        </div>
                                        <h4 class="h6 fw-bold mb-1">{{ $selectedPart->name }}</h4>
                                        <div class="d-flex justify-content-between align-items-center small mt-2 pt-2 border-top">
                                            <span class="text-muted">{{ $selectedPartQty }} {{ $selectedPart->unit }} &times; {{ $selectedPart->formattedPrice() }}</span>
                                            <span class="fw-bold text-accent">Rp {{ number_format($selectedPart->price * $selectedPartQty, 0, ',', '.') }}</span>
                                        </div>
                                    </div>
                                @endif

                                <div class="p-3 rounded-3 bg-body-tertiary border mb-3">
                                    <div class="row g-2 small">
                                        <div class="col-6">
                                            <span class="text-muted d-block">Estimasi Durasi</span>
                                            <strong id="previewServiceDuration" class="fs-6 text-body">
                                                {{ $selectedService ? ($selectedService->duration_minutes ?? 60).' Menit' : '-' }}
                                            </strong>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted d-block">Estimasi Selesai</span>
                                            <strong id="previewEstimatedEnd" class="fs-6 text-accent">
                                                Pilih jam kedatangan
                                            </strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center py-2 border-top border-bottom small mb-3">
                                    <span class="text-muted">Biaya Awal Jasa</span>
                                    <span id="previewServicePrice" class="fw-bold text-accent fs-6">
                                        {{ $selectedService?->price_from ? 'Mulai Rp '.number_format($selectedService->price_from, 0, ',', '.') : 'Hubungi Bengkel' }}
                                    </span>
                                </div>

                                <div class="small">
                                    <span class="text-muted d-block mb-1">Unit Kendaraan:</span>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-car-front text-muted"></i>
                                        <span id="previewVehicleSummary" class="fw-semibold">Belum diisi</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Placeholder Kosong --}}
                            <div id="servicePreviewPlaceholder" class="text-center py-4 {{ $selectedService ? 'd-none' : '' }}">
                                <div class="mb-3">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary p-3 border text-muted">
                                        <i class="bi bi-tools display-6"></i>
                                    </span>
                                </div>
                                <p class="text-muted small mb-0 px-2">
                                    Silakan pilih jenis layanan di samping untuk melihat ringkasan &amp; estimasi waktu pengerjaan.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Kartu Standar Layanan Bengkel --}}
                    <div class="card shadow-sm">
                        <div class="card-header bg-transparent py-3 border-bottom d-flex align-items-center">
                            <i class="bi bi-shield-check text-accent fs-5 me-2"></i>
                            <h2 class="h6 mb-0 fw-bold">Standar Layanan JAF Service</h2>
                        </div>
                        <div class="card-body p-3 p-lg-4">
                            <ul class="list-unstyled mb-3 small d-flex flex-column gap-3">
                                <li class="d-flex align-items-start gap-2">
                                    <span class="badge rounded-circle bg-accent-subtle text-accent p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">1</span>
                                    <div>
                                        <span class="fw-semibold">Teknisi Bersertifikasi:</span>
                                        <span class="text-muted d-block">Dikerjakan oleh teknisi ahli berpengalaman dengan SOP pabrikan resmi.</span>
                                    </div>
                                </li>
                                <li class="d-flex align-items-start gap-2">
                                    <span class="badge rounded-circle bg-accent-subtle text-accent p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">2</span>
                                    <div>
                                        <span class="fw-semibold">Suku Cadang Asli &amp; Bergaransi:</span>
                                        <span class="text-muted d-block">Jaminan 100% suku cadang dan pelumas genuine orisinal bergaransi.</span>
                                    </div>
                                </li>
                                <li class="d-flex align-items-start gap-2">
                                    <span class="badge rounded-circle bg-accent-subtle text-accent p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">3</span>
                                    <div>
                                        <span class="fw-semibold">Garansi Pengerjaan:</span>
                                        <span class="text-muted d-block">Garansi servis 1 bulan atau 1.000 KM setelah pengerjaan selesai.</span>
                                    </div>
                                </li>
                                <li class="d-flex align-items-start gap-2">
                                    <span class="badge rounded-circle bg-accent-subtle text-accent p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">4</span>
                                    <div>
                                        <span class="fw-semibold">Inspeksi Gratis 21 Titik:</span>
                                        <span class="text-muted d-block">Pengecekan umum gratis untuk aki, rem, ban, fluida, dan lampu kendaraan.</span>
                                    </div>
                                </li>
                            </ul>

                            <div class="p-3 rounded-3 bg-body-tertiary border small">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-geo-alt-fill text-accent"></i>
                                    <span class="fw-semibold font-heading">Lokasi Bengkel Resmi JAF Service</span>
                                </div>
                                <p class="text-muted mb-0">
                                    {{ config('dealer.address') ?: 'Showroom & Bengkel Utama JAF Dealer' }}
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
    <script src="{{ \App\Support\Asset::url('js/service-booking.js') }}"></script>
@endpush
