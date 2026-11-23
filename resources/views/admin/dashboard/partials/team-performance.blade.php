{{-- Widget Performa Tim Terbaik (Sales & Mekanik) --}}
@php
    $showRevenue = $showRevenue ?? false;
    $rupiah = fn (int $amount) => 'Rp ' . number_format($amount, 0, ',', '.');
@endphp

<section class="mb-4" aria-labelledby="team-performance-title">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 id="team-performance-title" class="dashboard-section-title mb-0">
            <i class="bi bi-award me-2"></i>Performa Tim Terbaik
        </h3>
        <span class="small text-muted">Peringkat &amp; Evaluasi Kinerja Tim</span>
    </div>

    <div class="row g-4">
        {{-- Kolom Kiri: Sales Terbaik --}}
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm border">
                <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-person-badge me-1"></i>
                        <h4 class="h6 mb-0">Sales Terbaik</h4>
                    </div>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill">Top Sales</span>
                </div>
                <div class="card-body p-3">
                    @if ($topSales->isEmpty())
                        <x-empty-state icon="bi-people" title="Belum ada data sales" message="Data penjualan sales akan tampil di sini." />
                    @else
                        <div class="d-flex flex-column gap-3">
                            @foreach ($topSales as $index => $sales)
                                @php
                                    $rank = $index + 1;
                                    $isTop = $rank === 1;
                                @endphp
                                <div @class([
                                    'p-3 rounded-3 border transition-all',
                                    'bg-white border-dark-subtle shadow-sm' => $isTop,
                                    'bg-body-tertiary border-secondary-subtle' => ! $isTop,
                                ])>
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                        <div class="d-flex align-items-center gap-2 min-w-0">
                                            @if ($isTop)
                                                <span class="badge bg-dark text-white fw-bold px-2 py-1 rounded-pill">
                                                    <i class="bi bi-trophy-fill me-1 text-warning"></i>Terbaik #1
                                                </span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis fw-bold px-2 py-1 rounded-pill border">
                                                    #{{ $rank }}
                                                </span>
                                            @endif
                                            <span class="fw-semibold text-truncate">{{ $sales->name }}</span>
                                        </div>
                                        @if ($showRevenue && ($sales->sales_revenue ?? 0) > 0)
                                            <span class="fw-bold text-body text-nowrap small">
                                                {{ $rupiah((int) $sales->sales_revenue) }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="d-flex flex-wrap align-items-center gap-2 small">
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-car-front me-1 text-muted"></i>{{ $sales->sold_units_count }} Unit Terjual
                                        </span>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-steering me-1 text-muted"></i>{{ $sales->completed_test_drives_count }} Test Drive Selesai
                                        </span>
                                        @if (! $showRevenue && ($sales->sales_revenue ?? 0) > 0)
                                            <span class="text-muted ms-auto">
                                                Omset: <span class="fw-medium text-body">{{ $rupiah((int) $sales->sales_revenue) }}</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Kolom Kanan: Mekanik Terbaik --}}
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm border">
                <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-wrench-adjustable me-1"></i>
                        <h4 class="h6 mb-0">Mekanik Terbaik</h4>
                    </div>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill">Top Mekanik</span>
                </div>
                <div class="card-body p-3">
                    @if ($topMechanics->isEmpty())
                        <x-empty-state icon="bi-tools" title="Belum ada data mekanik" message="Data kinerja mekanik akan tampil di sini." />
                    @else
                        <div class="d-flex flex-column gap-3">
                            @foreach ($topMechanics as $index => $mechanic)
                                @php
                                    $rank = $index + 1;
                                    $isTop = $rank === 1;
                                    $ratingVal = $mechanic->avg_rating !== null ? round((float) $mechanic->avg_rating, 1) : null;
                                @endphp
                                <div @class([
                                    'p-3 rounded-3 border transition-all',
                                    'bg-white border-dark-subtle shadow-sm' => $isTop,
                                    'bg-body-tertiary border-secondary-subtle' => ! $isTop,
                                ])>
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                        <div class="d-flex align-items-center gap-2 min-w-0">
                                            @if ($isTop)
                                                <span class="badge bg-dark text-white fw-bold px-2 py-1 rounded-pill">
                                                    <i class="bi bi-trophy-fill me-1 text-warning"></i>Terbaik #1
                                                </span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis fw-bold px-2 py-1 rounded-pill border">
                                                    #{{ $rank }}
                                                </span>
                                            @endif
                                            <span class="fw-semibold text-truncate">{{ $mechanic->name }}</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-1 text-nowrap">
                                            @if ($ratingVal !== null)
                                                <i class="bi bi-star-fill text-warning"></i>
                                                <span class="fw-bold text-body">{{ number_format($ratingVal, 1, ',', '.') }}</span>
                                                <span class="text-muted small">/ 5.0</span>
                                            @else
                                                <span class="text-muted small">Belum ada rating</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap align-items-center gap-2 small">
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-check-circle me-1 text-muted"></i>{{ $mechanic->completed_services_count }} Servis Selesai
                                        </span>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-chat-left-text me-1 text-muted"></i>{{ $mechanic->reviews_count }} Ulasan Customer
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
