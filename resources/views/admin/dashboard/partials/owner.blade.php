{{-- Dashboard Khusus Owner: Fokus Finansial / Pendapatan --}}
@php
    $rupiah = fn (int $amount) => 'Rp ' . number_format($amount, 0, ',', '.');
@endphp

{{-- Kartu Utama Finansial --}}
<section class="mb-4" aria-labelledby="owner-financial-title">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 id="owner-financial-title" class="dashboard-section-title mb-0">Pendapatan Bulan Ini</h3>
        <span class="small text-muted">{{ now()->translatedFormat('F Y') }}</span>
    </div>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3 mb-4">
        <div class="col">
            <x-stat-card label="Total Pendapatan Gabungan" :value="$rupiah($ownerStats['total_revenue'])" icon="bi-cash-stack" color="green" />
        </div>
        <div class="col">
            <x-stat-card label="Pendapatan Penjualan" :value="$rupiah($ownerStats['sales_revenue'])" icon="bi-car-front" color="red" />
        </div>
        <div class="col">
            <x-stat-card label="Pendapatan Servis" :value="$rupiah($ownerStats['service_revenue'])" icon="bi-wrench-adjustable" color="yellow" />
        </div>
    </div>

    <div class="row row-cols-1 row-cols-sm-3 g-3 mb-4">
        <div class="col">
            <x-stat-card label="Unit Terjual (Bulan Ini)" :value="$ownerStats['units_sold']" icon="bi-bag-check" color="navy" />
        </div>
        <div class="col">
            <x-stat-card label="Servis Selesai (Bulan Ini)" :value="$ownerStats['services_completed']" icon="bi-check-circle" color="blue" />
        </div>
        <div class="col">
            <x-stat-card label="Mobil Stok Habis" :value="$ownerStats['out_of_stock_cars']" icon="bi-box-seam" color="dark" />
        </div>
    </div>
</section>

{{-- Grafik Tren 6 Bulan (Chart.js & Tabel Cadangan) --}}
@include('admin.partials.dashboard-trend', ['trend' => $trend])

{{-- Tren Pendapatan 6 Bulan --}}
<section class="mb-4" aria-labelledby="owner-trend-title">
    <div class="card">
        <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
            <h4 id="owner-trend-title" class="h6 mb-0">Tren Finansial &amp; Aktivitas 6 Bulan Terakhir</h4>
            <span class="small text-muted">{{ $trend[0]['label'] }} – {{ end($trend)['label'] }}</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 admin-table">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th class="text-center">Unit Terjual</th>
                            <th class="text-end">Pendapatan Penjualan</th>
                            <th class="text-center">Servis Selesai</th>
                            <th class="text-end">Pendapatan Servis</th>
                            <th class="text-end fw-bold">Total Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trend as $m)
                            <tr>
                                <td class="fw-medium">{{ $m['label'] }}</td>
                                <td class="text-center">{{ $m['units_sold'] }}</td>
                                <td class="text-end">{{ $rupiah($m['sales_value']) }}</td>
                                <td class="text-center">{{ $m['services_completed'] }}</td>
                                <td class="text-end">{{ $rupiah($m['service_revenue']) }}</td>
                                <td class="text-end fw-bold text-success">{{ $rupiah($m['total_revenue']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
