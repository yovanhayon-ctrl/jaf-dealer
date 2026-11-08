{{-- Dashboard Khusus Customer Service --}}
@php
    $routeOrNull = fn (string $name, array $parameters = []) => Route::has($name) ? route($name, $parameters) : null;
@endphp

<section class="mb-4" aria-labelledby="cs-summary-title">
    <h3 id="cs-summary-title" class="dashboard-section-title">Pelayanan Pelanggan</h3>
    <div class="row row-cols-1 row-cols-sm-3 g-3 mb-4">
        <div class="col">
            <x-stat-card label="Booking Servis (Pending)" :value="$csStats['pending_service_bookings']" icon="bi-wrench-adjustable" color="yellow" alert
                         :href="$routeOrNull('admin.service-bookings.index', ['status' => 'pending'])" />
        </div>
        <div class="col">
            <x-stat-card label="Test Drive (Pending)" :value="$csStats['pending_test_drives']" icon="bi-calendar-event" color="yellow" alert
                         :href="$routeOrNull('admin.test-drives.index', ['status' => 'pending'])" />
        </div>
        <div class="col">
            <x-stat-card label="Ulasan Baru" :value="$csStats['new_testimonials']" icon="bi-star" color="blue" alert
                         :href="$routeOrNull('admin.testimonials.index', ['status' => 'pending'])" />
        </div>
    </div>
</section>

@if ($csStats['unconfirmed_test_drives_tomorrow'] > 0 || $csStats['unconfirmed_service_bookings_tomorrow'] > 0)
    <div class="dashboard-attention mb-4" role="status">
        <span class="dashboard-attention-title"><i class="bi bi-exclamation-triangle"></i>Perlu Konfirmasi Segera (Jadwal Besok)</span>
        <ul class="dashboard-attention-list">
            @if ($csStats['unconfirmed_test_drives_tomorrow'] > 0)
                <li>
                    <a href="{{ $routeOrNull('admin.test-drives.index', ['status' => 'pending', 'dari' => today()->addDay()->toDateString(), 'sampai' => today()->addDay()->toDateString()]) }}">
                        <strong>{{ $csStats['unconfirmed_test_drives_tomorrow'] }}</strong> test drive besok belum dikonfirmasi
                    </a>
                </li>
            @endif
            @if ($csStats['unconfirmed_service_bookings_tomorrow'] > 0)
                <li>
                    <a href="{{ $routeOrNull('admin.service-bookings.index', ['status' => 'pending', 'dari' => today()->addDay()->toDateString(), 'sampai' => today()->addDay()->toDateString()]) }}">
                        <strong>{{ $csStats['unconfirmed_service_bookings_tomorrow'] }}</strong> booking servis besok belum dikonfirmasi
                    </a>
                </li>
            @endif
        </ul>
    </div>
@endif

<div class="row g-4">
    {{-- Test Drive Menunggu Konfirmasi --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
                <h4 class="h6 mb-0">Test Drive Menunggu Konfirmasi</h4>
                @if ($url = $routeOrNull('admin.test-drives.index', ['status' => 'pending']))
                    <a href="{{ $url }}" class="small">Lihat semua</a>
                @endif
            </div>
            <div class="card-body p-0">
                @if ($upcomingTestDrives->isEmpty())
                    <x-empty-state icon="bi-check2-circle" title="Tidak ada antrean" message="Semua jadwal test drive sudah dikonfirmasi." />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 admin-table dashboard-table dashboard-table-compact">
                            <thead>
                                <tr>
                                    <th class="col-schedule">Rencana Jadwal</th>
                                    <th>Customer &amp; Mobil</th>
                                    <th class="col-status">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($upcomingTestDrives as $testDrive)
                                    @php($detailUrl = $routeOrNull('admin.test-drives.show', [$testDrive]))
                                    <tr @if ($detailUrl) data-row-link="{{ $detailUrl }}" @endif>
                                        <td class="text-nowrap">
                                            @if ($detailUrl)
                                                <a href="{{ $detailUrl }}" class="text-reset text-decoration-none fw-medium">{{ $testDrive->preferred_date->translatedFormat('d M Y') }}</a>
                                            @else
                                                {{ $testDrive->preferred_date->translatedFormat('d M Y') }}
                                            @endif
                                            <div class="small text-muted">{{ substr($testDrive->preferred_time, 0, 5) }} WIB</div>
                                        </td>
                                        <td>
                                            <span class="dashboard-truncate">{{ $testDrive->user->name }}</span>
                                            <span class="small text-muted dashboard-truncate">{{ $testDrive->car->brand->name }} {{ $testDrive->car->name }}</span>
                                        </td>
                                        <td>
                                            @if ($detailUrl)
                                                <a href="{{ $detailUrl }}" class="btn btn-outline-primary btn-sm">Konfirmasi</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Booking Servis Menunggu Konfirmasi --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
                <h4 class="h6 mb-0">Booking Servis Menunggu Konfirmasi</h4>
                @if ($url = $routeOrNull('admin.service-bookings.index', ['status' => 'pending']))
                    <a href="{{ $url }}" class="small">Lihat semua</a>
                @endif
            </div>
            <div class="card-body p-0">
                @if ($upcomingServiceBookings->isEmpty())
                    <x-empty-state icon="bi-check2-circle" title="Tidak ada antrean" message="Semua booking servis sudah dikonfirmasi." />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 admin-table dashboard-table dashboard-table-compact">
                            <thead>
                                <tr>
                                    <th class="col-schedule">Rencana Jadwal</th>
                                    <th>Customer &amp; Servis</th>
                                    <th class="col-status">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($upcomingServiceBookings as $booking)
                                    @php($detailUrl = $routeOrNull('admin.service-bookings.show', [$booking]))
                                    <tr @if ($detailUrl) data-row-link="{{ $detailUrl }}" @endif>
                                        <td class="text-nowrap">
                                            @if ($detailUrl)
                                                <a href="{{ $detailUrl }}" class="text-reset text-decoration-none fw-medium">{{ $booking->preferred_date->translatedFormat('d M Y') }}</a>
                                            @else
                                                {{ $booking->preferred_date->translatedFormat('d M Y') }}
                                            @endif
                                            <div class="small text-muted">{{ $booking->timeLabel() }} WIB</div>
                                        </td>
                                        <td>
                                            <span class="dashboard-truncate">{{ $booking->user->name }}</span>
                                            <span class="small text-muted dashboard-truncate">{{ $booking->service->name }}</span>
                                        </td>
                                        <td>
                                            @if ($detailUrl)
                                                <a href="{{ $detailUrl }}" class="btn btn-outline-primary btn-sm">Konfirmasi</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
