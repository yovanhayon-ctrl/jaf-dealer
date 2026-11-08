@extends('layouts.admin')

@section('title', 'Dashboard')

@php
    $panelUser = auth()->user();
    $role = $panelUser->role;
    $quickActions = array_filter([
        ['admin.cars.create', 'bi-plus-lg', 'Tambah Mobil', 'btn-accent', 'cars.manage'],
        ['admin.promos.create', 'bi-percent', 'Tambah Promo', 'btn-outline-primary', 'promos.manage'],
        ['admin.reports.index', 'bi-bar-chart', 'Laporan', 'btn-outline-primary', 'reports.view'],
    ], fn (array $action) => Route::has($action[0]) && $panelUser->can($action[4]));
@endphp

@section('content')
    {{-- Sambutan + aksi cepat --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 mb-1">Selamat datang, {{ $panelUser->name }}</h2>
            <p class="text-muted mb-0">{{ now()->translatedFormat('l, d F Y') }} <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">{{ $panelUser->roleLabel() }}</span></p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            {{-- Tombol Backup khusus Owner --}}
            @can('backup.manage')
                @if (Route::has('admin.settings.edit'))
                    <a href="{{ route('admin.settings.edit') }}#backup" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-shield-check"></i>Pengaturan Backup
                    </a>
                @endif
            @endcan

            {{-- Aksi cepat operasional untuk Super Admin & Admin Dealer --}}
            @if (! $panelUser->isOwner())
                @foreach ($quickActions as [$route, $icon, $label, $style, $ability])
                    <a href="{{ route($route) }}" class="btn {{ $style }} btn-sm"><i class="bi {{ $icon }}"></i>{{ $label }}</a>
                @endforeach

                {{-- Kirim Pengingat Besok --}}
                @can('reminders.send')
                    <form method="POST" action="{{ route('admin.reminders.send') }}" data-disable-on-submit
                          data-confirm="Kirim pengingat ke customer & mekanik untuk jadwal besok yang sudah dikonfirmasi dan batas pembayaran yang jatuh besok?">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-alarm"></i>Kirim Pengingat Besok</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    {{-- Cadangan otomatis (Hanya Owner): status cadangan --}}
    @if ($canSeeBackup)
        @php($backupStale = ! $latestBackup || $latestBackup['created_at']->lt(now()->subHours(\App\Actions\CreateBackup::STALE_AFTER_HOURS)))
        <p @class(['small mb-4', 'text-muted' => ! $backupStale, 'dashboard-backup-warning' => $backupStale]) role="status">
            <i @class(['bi', 'bi-shield-check' => ! $backupStale, 'bi-exclamation-triangle' => $backupStale])></i>
            @if ($latestBackup)
                Cadangan terakhir {{ $latestBackup['created_at']->translatedFormat('d M Y H:i') }}
                ({{ \Illuminate\Support\Number::fileSize($latestBackup['size'], 1) }}).
            @else
                Belum ada cadangan otomatis.
            @endif
            @if ($backupStale)
                Pastikan cron <code>schedule:run</code> aktif di hosting, atau jalankan <code>php artisan backup:run</code>.
            @endif
        </p>
    @endif

    {{-- Konten Dashboard sesuai Role --}}
    @if ($roleView === 'owner')
        @include('admin.dashboard.partials.owner')
    @elseif ($roleView === 'sales')
        @include('admin.dashboard.partials.sales')
    @elseif ($roleView === 'customer_service')
        @include('admin.dashboard.partials.customer-service')
    @else
        @include('admin.dashboard.partials.operational')
    @endif
@endsection
