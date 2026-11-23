{{-- Dashboard Operasional: Super Admin & Admin Dealer (Tanpa Angka Rupiah) --}}
@php
    $routeOrNull = fn (string $name, array $parameters = []) => Route::has($name) ? route($name, $parameters) : null;
    $attention = array_filter([
        [$stats['awaiting_response'], 'pengajuan belum ditanggapi', 'admin.purchase-requests.index', ['tindak' => 'belum-ditanggapi'], 'purchases.status'],
        [$stats['overdue_payments'], 'pengajuan lewat tenggat bayar', 'admin.purchase-requests.index', ['pembayaran' => 'lewat-tenggat'], 'purchases.status'],
        [$stats['unconfirmed_test_drives_tomorrow'], 'test drive besok belum dikonfirmasi', 'admin.test-drives.index', ['status' => 'pending', 'dari' => today()->addDay()->toDateString(), 'sampai' => today()->addDay()->toDateString()], 'test-drives.manage'],
        [$stats['unconfirmed_service_bookings_tomorrow'], 'booking servis besok belum dikonfirmasi', 'admin.service-bookings.index', ['status' => 'pending', 'dari' => today()->addDay()->toDateString(), 'sampai' => today()->addDay()->toDateString()], 'service-bookings.manage'],
        [$stats['overdue_test_drives'], 'test drive lewat jadwal', 'admin.test-drives.index', ['jadwal' => 'lewat'], 'test-drives.manage'],
        [$stats['overdue_service_bookings'], 'booking servis lewat jadwal', 'admin.service-bookings.index', ['jadwal' => 'lewat'], 'service-bookings.manage'],
        [$stats['pending_refunds'], 'pengajuan batal, dana belum dikembalikan', 'admin.purchase-requests.index', ['pembayaran' => 'dana-belum-dikembalikan'], 'payments.manage'],
        [$stats['low_stock_spare_parts'], 'suku cadang stok menipis / perlu restock', 'admin.spare-parts.index', ['stok' => 'menipis'], 'spare-parts.view'],
    ], fn (array $item) => $item[0] > 0 && auth()->user()->can($item[4]));
@endphp

{{-- Perlu ditindaklanjuti --}}
@if ($attention)
    <div class="dashboard-attention mb-4" role="status">
        <span class="dashboard-attention-title"><i class="bi bi-exclamation-triangle"></i>Perlu ditindaklanjuti</span>
        <ul class="dashboard-attention-list">
            @foreach ($attention as [$count, $label, $route, $parameters, $ability])
                <li><a href="{{ $routeOrNull($route, $parameters) }}"><strong>{{ $count }}</strong> {{ $label }}</a></li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Ringkasan Operasional --}}
<section class="mb-4" aria-labelledby="summary-title">
    <h3 id="summary-title" class="dashboard-section-title">Ringkasan Operasional</h3>
    <div class="stat-grid">
        @can('cars.view')
            <x-stat-card label="Mobil Aktif" :value="$stats['active_cars']" icon="bi-car-front" color="navy"
                         :href="$routeOrNull('admin.cars.index', ['status' => 'aktif'])" />
            <x-stat-card label="Stok Habis" :value="$stats['out_of_stock_cars']" icon="bi-box-seam" color="dark"
                         :href="$routeOrNull('admin.cars.index', ['status' => 'aktif', 'stok' => 'habis'])" />
        @endcan
        @can('test-drives.view')
            <x-stat-card label="Test Drive Pending" :value="$stats['pending_test_drives']" icon="bi-calendar-event" color="yellow" alert
                         :href="$routeOrNull('admin.test-drives.index', ['status' => 'pending'])" />
        @endcan
        @can('purchases.view')
            <x-stat-card label="Pengajuan Pending" :value="$stats['pending_purchases']" icon="bi-hourglass-split" color="yellow" alert
                         :href="$routeOrNull('admin.purchase-requests.index', ['status' => 'pending'])" />
        @endcan
        @can('service-bookings.manage')
            <x-stat-card label="Servis Pending" :value="$stats['pending_service_bookings']" icon="bi-wrench-adjustable" color="yellow" alert
                         :href="$routeOrNull('admin.service-bookings.index', ['status' => 'pending'])" />
        @endcan
        @can('spare-parts.view')
            @if ($stats['low_stock_spare_parts'] > 0)
                <x-stat-card label="Suku Cadang Menipis" :value="$stats['low_stock_spare_parts']" icon="bi-exclamation-diamond" color="red" alert
                             :href="$routeOrNull('admin.spare-parts.index', ['stok' => 'menipis'])" />
            @else
                <x-stat-card label="Suku Cadang" :value="$stats['active_spare_parts']" icon="bi-tools" color="navy"
                             :href="$routeOrNull('admin.spare-parts.index')" />
            @endif
        @endcan
        @can('users.view')
            <x-stat-card label="Customer" :value="$stats['customers']" icon="bi-people" color="navy"
                         :href="$routeOrNull('admin.users.index', ['role' => 'customer'])" />
        @endcan
    </div>
</section>

{{-- Daftar Cepat --}}
<section aria-labelledby="quick-lists-title">
    <h3 id="quick-lists-title" class="visually-hidden">Daftar Aktivitas Operasional</h3>
    <div class="row g-4">
        @can('purchases.view')
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-header dashboard-card-header">
                        <h4 class="h6 mb-0">Pengajuan Pembelian Terbaru</h4>
                        @if ($url = $routeOrNull('admin.purchase-requests.index'))
                            <a href="{{ $url }}" class="small">Lihat semua</a>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        @if ($latestPurchases->isEmpty())
                            <x-empty-state icon="bi-file-earmark-text" title="Belum ada pengajuan" message="Pengajuan pembelian dari customer akan muncul di sini." />
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 admin-table dashboard-table">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Customer</th>
                                            <th>Mobil</th>
                                            <th>Metode</th>
                                            <th class="col-status">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($latestPurchases as $purchase)
                                            @php($detailUrl = $routeOrNull('admin.purchase-requests.show', [$purchase]))
                                            <tr @if ($detailUrl) data-row-link="{{ $detailUrl }}" @endif>
                                                <td class="text-nowrap">
                                                    @if ($detailUrl)
                                                        <a href="{{ $detailUrl }}" class="text-reset text-decoration-none fw-medium">{{ $purchase->created_at->translatedFormat('d M Y') }}</a>
                                                    @else
                                                        {{ $purchase->created_at->translatedFormat('d M Y') }}
                                                    @endif
                                                    <div class="small text-muted">{{ $purchase->created_at->translatedFormat('H:i') }} WIB</div>
                                                </td>
                                                <td><span class="dashboard-truncate">{{ $purchase->user->name }}</span></td>
                                                <td><span class="dashboard-truncate">{{ $purchase->car->brand->name }} {{ $purchase->car->name }}</span></td>
                                                <td><span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $purchase->paymentMethodLabel() }}</span></td>
                                                <td><x-status-badge :status="$purchase->status" /></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endcan

        @can('test-drives.view')
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header dashboard-card-header">
                        <h4 class="h6 mb-0">Jadwal Test Drive Terdekat</h4>
                        @if ($url = $routeOrNull('admin.test-drives.index'))
                            <a href="{{ $url }}" class="small">Lihat semua</a>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        @if ($upcomingTestDrives->isEmpty())
                            <x-empty-state icon="bi-calendar-x" title="Belum ada jadwal test drive" message="Jadwal test drive mendatang akan muncul di sini." />
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 admin-table dashboard-table dashboard-table-compact">
                                    <thead>
                                        <tr>
                                            <th class="col-schedule">Jadwal</th>
                                            <th>Customer &amp; Mobil</th>
                                            <th class="col-status">Status</th>
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
                                                <td><x-status-badge :status="$testDrive->status" /></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endcan

        @can('service-bookings.manage')
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header dashboard-card-header">
                        <h4 class="h6 mb-0">Booking Servis Terdekat</h4>
                        @if ($url = $routeOrNull('admin.service-bookings.index'))
                            <a href="{{ $url }}" class="small">Lihat semua</a>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        @if ($upcomingServiceBookings->isEmpty())
                            <x-empty-state icon="bi-wrench-adjustable" title="Belum ada jadwal servis" message="Booking servis mendatang akan muncul di sini." />
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 admin-table dashboard-table dashboard-table-compact">
                                    <thead>
                                        <tr>
                                            <th class="col-schedule">Jadwal</th>
                                            <th>Customer &amp; Servis</th>
                                            <th class="col-status">Status</th>
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
                                                    <span class="small text-muted dashboard-truncate">{{ $booking->vehicle_model }} · {{ $booking->plate_number }}</span>
                                                    <span class="small dashboard-truncate"><i class="bi bi-wrench-adjustable me-1"></i>{{ $booking->service->name }}</span>
                                                </td>
                                                <td><x-status-badge :status="$booking->status" /></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endcan
    </div>
</section>

{{-- Widget Performa Tim: Sales & Mekanik Terbaik --}}
@include('admin.dashboard.partials.team-performance', ['showRevenue' => false])
