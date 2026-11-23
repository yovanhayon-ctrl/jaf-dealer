@extends('layouts.admin')

@section('title', 'Ulasan')

@section('breadcrumb')
    @include('partials.breadcrumb', ['items' => [['label' => 'Ulasan']]])
@endsection

@php
    $filters = [null => 'Semua'] + \App\Models\Testimonial::STATUS_LABELS;
    $ratingFilters = [null => 'Semua', 5 => '5 Bintang', 4 => '4 Bintang', 3 => '3 Bintang', 2 => '2 Bintang', 1 => '1 Bintang'];
@endphp

@section('content')
    <ul class="nav nav-tabs mb-4" aria-label="Tipe Ulasan">
        <li class="nav-item">
            <a @class(['nav-link', 'active' => $tipe === 'mobil']) href="{{ route('admin.testimonials.index', ['tipe' => 'mobil']) }}">
                <i class="bi bi-car-front me-1"></i>Ulasan Pembelian Mobil
                <span class="badge rounded-pill {{ $tipe === 'mobil' ? 'bg-primary text-white' : 'badge-status-muted' }} ms-1">{{ $totalCarTestimonials }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a @class(['nav-link', 'active' => $tipe === 'servis']) href="{{ route('admin.testimonials.index', ['tipe' => 'servis']) }}">
                <i class="bi bi-wrench-adjustable me-1"></i>Ulasan Servis Mekanik
                <span class="badge rounded-pill {{ $tipe === 'servis' ? 'bg-primary text-white' : 'badge-status-muted' }} ms-1">{{ $totalServiceRatings }}</span>
            </a>
        </li>
    </ul>

    @if ($tipe === 'servis')
        <p class="text-muted small mb-3">Ulasan dan rating customer untuk servis kendaraan yang dikerjakan oleh mekanik.</p>

        <ul class="nav nav-pills gap-1 mb-3" aria-label="Filter bintang ulasan servis">
            @foreach ($ratingFilters as $value => $label)
                @php($isActive = ($selectedRating === null && $value === null) || ($selectedRating !== null && $selectedRating === $value))
                <li class="nav-item">
                    <a @class(['nav-link py-1 px-3', 'active' => $isActive])
                       href="{{ route('admin.testimonials.index', array_filter(['tipe' => 'servis', 'rating' => $value])) }}"
                       @if ($isActive) aria-current="page" @endif>
                        @if ($value !== null)
                            <i class="bi bi-star-fill text-warning me-1"></i>{{ $value }}
                        @else
                            {{ $label }}
                        @endif
                        <span class="badge rounded-pill {{ $isActive ? 'bg-light text-dark' : 'badge-status-muted' }} ms-1">
                            {{ $value !== null ? (int) ($ratingCounts[$value] ?? 0) : (int) $totalServiceRatings }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($mechanicRatings->isEmpty())
            <div class="card">
                <x-empty-state icon="bi-wrench-adjustable" title="Belum ada ulasan servis"
                               message="Ulasan servis muncul setelah customer menilai servis yang sudah selesai dikerjakan." />
            </div>
        @else
            <div class="d-flex flex-column gap-3">
                @foreach ($mechanicRatings as $rating)
                    @php($booking = $rating->booking)
                    <article class="card" id="ulasan-servis-{{ $rating->id }}">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="fw-semibold">{{ $rating->user->name }}</span>
                                <span class="text-muted small">{{ $rating->user->email }}</span>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-person-gear me-1"></i>Mekanik: {{ $rating->mechanic?->name ?? 'Belum Ditugaskan' }}
                                </span>
                                <small class="text-muted ms-auto">{{ $rating->created_at->translatedFormat('d M Y H:i') }}</small>
                            </div>

                            <p class="small mb-2">
                                <x-rating-stars :rating="$rating->rating" class="me-2 text-warning" />
                                <span class="fw-medium">{{ $booking?->service?->name ?? 'Servis' }}</span>
                                @if ($booking?->vehicle_model)
                                    · {{ $booking->vehicle_model }}
                                @endif
                                @if ($booking?->plate_number)
                                    <span class="text-muted">({{ $booking->plate_number }})</span>
                                @endif
                                @if ($booking && Route::has('admin.service-bookings.show'))
                                    · <a href="{{ route('admin.service-bookings.show', $booking->id) }}">Lihat booking servis</a>
                                @endif
                            </p>

                            @if ($rating->comment)
                                <p class="mb-0 text-break">“{{ $rating->comment }}”</p>
                            @else
                                <p class="mb-0 text-muted fst-italic small">Tanpa komentar tertulis</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($mechanicRatings->hasPages())
                <div class="mt-4">
                    {{ $mechanicRatings->onEachSide(1)->links('partials.pagination-links') }}
                </div>
            @endif
        @endif

    @else
        <p class="text-muted small mb-3">Ulasan customer untuk pembelian yang sudah selesai. Hanya ulasan <span class="fw-semibold">Disetujui</span> yang tampil di beranda.</p>

        <ul class="nav nav-pills gap-1 mb-3" aria-label="Filter status ulasan">
            @foreach ($filters as $value => $label)
                @php($isActive = ($status ?? '') === (string) $value)
                <li class="nav-item">
                    <a @class(['nav-link py-1 px-3', 'active' => $isActive]) href="{{ route('admin.testimonials.index', array_filter(['tipe' => 'mobil', 'status' => $value])) }}"
                       @if ($isActive) aria-current="page" @endif>
                        {{ $label }}
                        <span class="badge rounded-pill {{ $isActive ? 'bg-light text-dark' : 'badge-status-muted' }} ms-1">{{ $value ? (int) ($counts[$value] ?? 0) : (int) $counts->sum() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($testimonials->isEmpty())
            <div class="card">
                <x-empty-state icon="bi-star" title="Belum ada ulasan mobil" message="Ulasan muncul setelah customer menilai pembelian yang sudah selesai." />
            </div>
        @else
            <div class="d-flex flex-column gap-3">
                @foreach ($testimonials as $testimonial)
                    @php($car = $testimonial->purchaseRequest->car)
                    @php($bag = $errors->getBag("reject{$testimonial->id}"))
                    <article class="card" id="ulasan-{{ $testimonial->id }}">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="fw-semibold">{{ $testimonial->user->name }}</span>
                                <span class="text-muted small">{{ $testimonial->user->email }}</span>
                                <x-status-badge :status="$testimonial->status" />
                                <small class="text-muted ms-auto">{{ $testimonial->updated_at->translatedFormat('d M Y H:i') }}</small>
                            </div>
                            <p class="small mb-2">
                                <x-rating-stars :rating="$testimonial->rating" class="me-2" />
                                {{ $car->brand->name }} {{ $car->name }} {{ $car->year }}
                                @if (Route::has('admin.purchase-requests.show'))
                                    · <a href="{{ route('admin.purchase-requests.show', $testimonial->purchase_request_id) }}">Lihat pengajuan</a>
                                @endif
                            </p>
                            <p class="mb-2 text-break">{{ $testimonial->comment }}</p>

                            @if ($testimonial->status === \App\Models\Testimonial::STATUS_REJECTED && $testimonial->admin_note)
                                <p class="small text-danger mb-2"><span class="fw-semibold">Alasan ditolak:</span> {{ $testimonial->admin_note }}</p>
                            @endif

                            {{-- Customer Service hanya melihat; moderasi untuk testimonials.moderate. --}}
                            @can('testimonials.moderate')
                                <div class="d-flex flex-wrap gap-2">
                                    @unless ($testimonial->isApproved())
                                        <form method="POST" action="{{ route('admin.testimonials.approve', $testimonial) }}" data-disable-on-submit>
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i>Setujui</button>
                                        </form>
                                    @endunless
                                    @if ($testimonial->status !== \App\Models\Testimonial::STATUS_REJECTED)
                                        <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#reject-{{ $testimonial->id }}" aria-expanded="{{ $bag->any() ? 'true' : 'false' }}"
                                                aria-controls="reject-{{ $testimonial->id }}">
                                            <i class="bi bi-x-lg"></i>{{ $testimonial->isApproved() ? 'Sembunyikan (Tolak)' : 'Tolak' }}
                                        </button>
                                    @endif
                                </div>

                                @if ($testimonial->status !== \App\Models\Testimonial::STATUS_REJECTED)
                                    <form method="POST" action="{{ route('admin.testimonials.reject', $testimonial) }}"
                                          @class(['collapse mt-2', 'show' => $bag->any()]) id="reject-{{ $testimonial->id }}" data-disable-on-submit>
                                        @csrf
                                        @method('PATCH')
                                        <label for="admin_note-{{ $testimonial->id }}" class="form-label small">Alasan penolakan (dikirim ke customer)</label>
                                        <textarea id="admin_note-{{ $testimonial->id }}" name="admin_note" rows="2" maxlength="500" required
                                                  @class(['form-control form-control-sm', 'is-invalid' => $bag->has('admin_note')])
                                                  placeholder="Contoh: Ulasan berisi nomor HP. Mohon hapus data pribadi.">{{ $bag->any() ? old('admin_note') : '' }}</textarea>
                                        @if ($bag->has('admin_note'))
                                            <div class="invalid-feedback">{{ $bag->first('admin_note') }}</div>
                                        @endif
                                        <button type="submit" class="btn btn-danger btn-sm mt-2">Tolak Ulasan</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($testimonials->hasPages())
                <div class="mt-4">
                    {{ $testimonials->onEachSide(1)->links('partials.pagination-links') }}
                </div>
            @endif
        @endif
    @endif
@endsection
