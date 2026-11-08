@props(['part'])

@php
    $detailUrl = route('spare-parts.show', $part);
@endphp

<article class="card car-card card-hover h-100">
    <div class="car-card-media">
        <a href="{{ $detailUrl }}" tabindex="-1" aria-hidden="true">
            @if ($part->image)
                <img src="{{ $part->imageUrl() }}" alt="{{ $part->name }}" class="car-card-img" loading="lazy">
            @else
                <div class="car-card-img car-card-img-empty" role="img" aria-label="Belum ada foto {{ $part->name }}">
                    <i class="bi bi-box-seam"></i>
                </div>
            @endif
        </a>

        <div class="car-card-badges">
            <span class="badge rounded-pill badge-condition-used">{{ $part->categoryLabel() }}</span>
            @if ($part->isOutOfStock())
                <span class="badge rounded-pill badge-status-red">Stok Habis</span>
            @endif
        </div>
    </div>

    <div class="card-body d-flex flex-column p-3">
        <p class="small text-muted mb-1 font-monospace">{{ $part->part_number }}</p>
        <h3 class="h6 mb-2 car-card-title">
            <a href="{{ $detailUrl }}" class="stretched-link text-reset text-decoration-none">{{ $part->name }}</a>
        </h3>

        @if ($part->compatibility)
            <p class="small text-muted mb-3 text-truncate" title="{{ $part->compatibility }}">
                <i class="bi bi-car-front"></i> {{ $part->compatibility }}
            </p>
        @else
            <p class="small text-muted mb-3 text-truncate">
                <i class="bi bi-shield-check"></i> 100% Genuine Part
            </p>
        @endif

        <div class="mt-auto">
            <div class="price-final mb-1">{{ $part->formattedPrice() }}</div>
            <p class="small text-muted mb-3">/ {{ $part->unit }} @if (! $part->isOutOfStock()) · <span class="text-success">Stok: {{ $part->stock }}</span>@endif</p>

            <div class="d-flex gap-2">
                <a href="{{ $detailUrl }}" class="btn btn-outline-primary btn-sm flex-grow-1">Detail</a>
                @if ($orderUrl = $part->whatsappOrderUrl())
                    <a href="{{ $orderUrl }}" target="_blank" rel="noopener" class="btn btn-outline-success btn-sm position-relative z-2" title="Pesan via WhatsApp">
                        <i class="bi bi-whatsapp"></i> Pesan
                    </a>
                @endif
            </div>
        </div>
    </div>
</article>
