{{-- Dashboard Khusus Sales --}}
@php
    $routeOrNull = fn (string $name, array $parameters = []) => Route::has($name) ? route($name, $parameters) : null;
    $salesAwaiting = \App\Models\PurchaseRequest::query()
        ->where('sales_id', auth()->id())
        ->awaitingResponse()
        ->count();
    $attention = array_filter([
        [$salesAwaiting, 'pengajuan belum ditanggapi', 'admin.purchase-requests.index', ['tindak' => 'belum-ditanggapi'], 'purchases.status'],
    ], fn (array $item) => $item[0] > 0 && auth()->user()->can($item[4]));
@endphp

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

<section class="mb-4" aria-labelledby="sales-summary-title">
    <h3 id="sales-summary-title" class="dashboard-section-title">Tugas Saya</h3>
    <div class="row row-cols-1 row-cols-sm-2 g-3 mb-4">
        <div class="col">
            <x-stat-card label="Test Drive Saya (Pending)" :value="$salesStats['pending_test_drives']" icon="bi-calendar-event" color="yellow" alert
                         :href="$routeOrNull('admin.test-drives.index', ['status' => 'pending'])" />
        </div>
        <div class="col">
            <x-stat-card label="Pengajuan Saya (Pending)" :value="$salesStats['pending_purchases']" icon="bi-hourglass-split" color="yellow" alert
                         :href="$routeOrNull('admin.purchase-requests.index', ['status' => 'pending'])" />
        </div>
    </div>
</section>

<div class="row g-4">
    {{-- Jadwal Test Drive Hari Ini & Besok --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
                <h4 class="h6 mb-0">Jadwal Test Drive Saya (Hari Ini &amp; Besok)</h4>
                @if ($url = $routeOrNull('admin.test-drives.index'))
                    <a href="{{ $url }}" class="small">Semua test drive</a>
                @endif
            </div>
            <div class="card-body p-0">
                @if ($upcomingTestDrives->isEmpty())
                    <x-empty-state icon="bi-calendar-check" title="Tidak ada jadwal test drive" message="Tidak ada jadwal test drive untuk hari ini atau besok." />
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
                                                <a href="{{ $detailUrl }}" class="text-reset text-decoration-none fw-medium">{{ $testDrive->preferred_date->isToday() ? 'Hari Ini' : 'Besok' }}</a>
                                            @else
                                                {{ $testDrive->preferred_date->isToday() ? 'Hari Ini' : 'Besok' }}
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

    {{-- Pengajuan Pembelian Terbaru Milik Sales --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
                <h4 class="h6 mb-0">Pengajuan Pembelian Saya</h4>
                @if ($url = $routeOrNull('admin.purchase-requests.index'))
                    <a href="{{ $url }}" class="small">Semua pengajuan</a>
                @endif
            </div>
            <div class="card-body p-0">
                @if ($latestPurchases->isEmpty())
                    <x-empty-state icon="bi-file-earmark-text" title="Belum ada pengajuan" message="Pengajuan yang ditugaskan kepada Anda akan muncul di sini." />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 admin-table dashboard-table dashboard-table-compact">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Customer &amp; Mobil</th>
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
                                        <td>
                                            <span class="dashboard-truncate">{{ $purchase->user->name }}</span>
                                            <span class="small text-muted dashboard-truncate">{{ $purchase->car->brand->name }} {{ $purchase->car->name }}</span>
                                        </td>
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
</div>
