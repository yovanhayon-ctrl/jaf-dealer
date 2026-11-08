@props([
    'label',
    'value',
    'icon',
    'color' => 'navy', // navy | red | yellow | green | blue | dark
    'href' => null,
    'alert' => false, // true: titik merah bila nilainya > 0 (ada yang perlu ditindaklanjuti)
])

@php
    $tag = $href ? 'a' : 'div';
    $needsAction = $alert && is_numeric($value) && $value > 0;
    $formattedValue = is_numeric($value) ? number_format($value, 0, ',', '.') : (string) $value;
    $isLong = mb_strlen($formattedValue) >= 10;
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['card stat-card h-100 text-decoration-none', 'card-hover' => $href]) }}>
    <div class="card-body d-flex align-items-center gap-3 p-3">
        <span class="stat-card-icon stat-card-icon-{{ $color }} flex-shrink-0">
            <i class="bi {{ $icon }}"></i>
        </span>
        <div class="min-w-0 flex-grow-1">
            <div @class([
                'stat-card-value font-heading text-nowrap',
                'stat-card-value-sm' => $isLong,
            ])>
                {{ $formattedValue }}
                @if ($needsAction)
                    <span class="stat-card-alert" title="Perlu ditindaklanjuti"><span class="visually-hidden">perlu ditindaklanjuti</span></span>
                @endif
            </div>
            <div class="small text-muted stat-card-label text-truncate" title="{{ $label }}">{{ $label }}</div>
        </div>
    </div>
</{{ $tag }}>
