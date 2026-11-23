@extends('layouts.admin')

@section('title', $booking->plate_number)

@use('App\Models\ServiceBooking')
@use('App\Models\ServiceBookingTask')

@php
    [$handled, $total] = $booking->taskProgress();
    $working = $booking->status === ServiceBooking::STATUS_IN_PROGRESS;
    $findingsErrors = $errors->getBag('findings');
@endphp

@section('breadcrumb')
    @include('partials.breadcrumb', ['items' => [['label' => $booking->plate_number]]])
@endsection

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h2 class="h5 mb-0">{{ $booking->vehicle_model }}@if ($booking->vehicle_year) ({{ $booking->vehicle_year }})@endif</h2>
        <x-status-badge :status="$booking->status" />
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-2">
                        <h3 class="admin-card-title mb-0">Checklist Pekerjaan</h3>
                        @if ($total > 0)
                            <span class="small text-muted">{{ $handled }} dari {{ $total }} ditangani</span>
                        @endif
                    </div>

                    <div class="alert alert-light border small mb-3">
                        <i class="bi bi-info-circle me-1 text-primary"></i>Checklist dan status pekerjaan dikelola oleh Admin. Silakan lakukan pekerjaan servis sesuai daftar tugas di bawah ini.
                    </div>

                    @forelse ($booking->tasks as $task)
                        <div @class(['mechanic-task', 'is-done' => $task->isDone(), 'is-skipped' => $task->isSkipped()]) id="tugas-{{ $task->id }}">
                            <div class="mechanic-task-name">
                                <i @class([
                                    'bi me-1',
                                    'bi-check-circle-fill text-success' => $task->isDone(),
                                    'bi-dash-circle text-muted' => $task->isSkipped(),
                                    'bi-circle text-muted' => $task->isPending(),
                                ]) aria-hidden="true"></i>
                                <span @class(['fw-semibold' => $task->isPending()])>{{ $task->name }}</span>
                                @if ($task->isSkipped())
                                    <div class="small text-muted">Dilewati: {{ $task->note }}</div>
                                @elseif ($task->isDone())
                                    <span class="small text-muted ms-1">· selesai {{ $task->checked_at?->translatedFormat('H:i') }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="small text-muted mb-0">Checklist kosong. Hubungi admin untuk informasi pekerjaan.</p>
                    @endforelse
                </div>
            </div>

            @if ($booking->findings)
                <div class="card mb-4" id="temuan">
                    <div class="card-body p-4">
                        <h3 class="admin-card-title mb-2">Temuan & Rekomendasi</h3>
                        <p class="small text-muted mb-0">{{ $booking->findings }}</p>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="card admin-sticky-panel">
                <div class="card-body p-4">
                    <dl class="admin-detail-list mb-3">
                        <dt>Plat nomor</dt>
                        <dd class="fw-semibold">{{ $booking->plate_number }}</dd>

                        <dt>Layanan</dt>
                        <dd>{{ $booking->service->name }}@if ($booking->service->duration_minutes), sekitar {{ $booking->service->duration_minutes }} menit @endif</dd>

                        <dt>Jadwal</dt>
                        <dd>{{ $booking->preferred_date->translatedFormat('l, d M Y') }} pukul {{ $booking->timeLabel() }}</dd>

                        <dt>Kilometer</dt>
                        <dd>{{ $booking->mileage !== null ? number_format($booking->mileage, 0, ',', '.').' km' : '-' }}</dd>

                        <dt>Customer</dt>
                        <dd>{{ $booking->user->name }}</dd>

                        <dt>Keluhan</dt>
                        <dd>{{ $booking->complaint ?: '-' }}</dd>
                    </dl>

                    <div class="pt-2 border-top">
                        <span class="text-muted small d-block mb-1">Status Pengerjaan:</span>
                        <x-status-badge :status="$booking->status" />
                        @if ($booking->status === ServiceBooking::STATUS_COMPLETED && $booking->finished_at)
                            <p class="small text-muted mt-2 mb-0">
                                <i class="bi bi-check-circle text-success me-1"></i>Selesai {{ $booking->finished_at->translatedFormat('d M Y H:i') }} WIB
                            </p>
                        @elseif ($booking->status === ServiceBooking::STATUS_IN_PROGRESS && $booking->started_at)
                            <p class="small text-muted mt-2 mb-0">
                                <i class="bi bi-gear-wide-connected text-primary me-1"></i>Dimulai {{ $booking->started_at->translatedFormat('d M Y H:i') }} WIB
                            </p>
                        @else
                            <p class="small text-muted mt-2 mb-0">Pengerjaan dikelola oleh Admin.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
