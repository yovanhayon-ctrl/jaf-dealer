<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nota Checklist & SPK Servis #{{ $booking->id }} - {{ $booking->plate_number }} | {{ config('dealer.name') }}</title>
    @include('partials.head', ['noindex' => true, 'forceLight' => true])
    <style>
        body {
            background-color: #e9ecef;
            color: #111;
            font-family: 'Consolas', 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.35;
        }
        .thermal-receipt {
            width: 350px;
            margin: 24px auto;
            background: #ffffff;
            padding: 24px 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            border-radius: 4px;
        }
        .receipt-dashed {
            border-top: 1px dashed #000000;
            margin: 9px 0;
        }
        .receipt-code-box {
            border: 1px solid #198754;
            color: #198754;
            padding: 2px 8px;
            display: inline-block;
            font-size: 11px;
            letter-spacing: 0.5px;
            font-weight: bold;
            border-radius: 2px;
        }
        .receipt-check-box {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1px solid #000;
            text-align: center;
            line-height: 11px;
            font-size: 10px;
            vertical-align: middle;
            margin-right: 4px;
        }
        @media print {
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .thermal-receipt {
                width: 100% !important;
                max-width: 80mm !important;
                margin: 0 auto !important;
                padding: 4mm 2mm !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
            }
            .d-print-none {
                display: none !important;
            }
            @page {
                size: 80mm auto;
                margin: 2mm;
            }
        }
    </style>
</head>
<body>
    {{-- Action Bar (Disembunyikan saat dicetak) --}}
    <div class="d-print-none bg-dark text-white py-2 px-3 sticky-top shadow-sm">
        <div class="container d-flex align-items-center justify-content-between" style="max-width: 420px;">
            <a href="{{ route('admin.service-bookings.show', $booking) }}" class="btn btn-sm btn-outline-light py-1 px-2">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
            <span class="small text-white-50">Nota Checklist Servis #{{ $booking->id }}</span>
            <button type="button" class="btn btn-sm btn-success py-1 px-3 fw-bold" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Cetak Nota
            </button>
        </div>
    </div>

    {{-- Kertas Struk Kasir / Thermal Receipt --}}
    <div class="thermal-receipt">
        {{-- Header Toko / Bengkel --}}
        <div class="text-center mb-2">
            <div class="fs-2 mb-1"><i class="bi bi-shop"></i></div>
            <div class="fw-bold fs-6 text-uppercase">{{ config('dealer.name') }}</div>
            <div class="text-muted" style="font-size: 11px;">{{ config('dealer.address') ?: 'Bengkel & Perawatan Mobil' }}</div>
            @if (config('dealer.phone') || config('dealer.whatsapp'))
                <div class="text-muted" style="font-size: 11px;">
                    No. Telp {{ config('dealer.phone') ?: ('0'.config('dealer.whatsapp')) }}
                </div>
            @endif
            <div class="mt-1">
                <span class="receipt-code-box">SPK-SRV-{{ str_pad($booking->id, 6, '0', STR_PAD_LEFT) }}</span>
            </div>
            <div class="fw-bold mt-1 text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                NOTA CHECKLIST & STRUK KERJA MEKANIK
            </div>
        </div>

        <div class="receipt-dashed"></div>

        {{-- Meta Waktu & Customer --}}
        <div class="d-flex justify-content-between" style="font-size: 11.5px;">
            <div>
                <div>{{ $booking->preferred_date->format('Y-m-d') }}</div>
                <div>{{ $booking->timeLabel() }} WIB</div>
                <div class="text-muted mt-1">No. #{{ $booking->id }}</div>
            </div>
            <div class="text-end">
                <div class="fw-bold">{{ $booking->user->name }}</div>
                <div>{{ $booking->phone }}</div>
                <div class="text-muted mt-1">Mekanik: <strong>{{ $booking->mechanic?->name ?? 'Belum Ditugaskan' }}</strong></div>
            </div>
        </div>

        <div class="receipt-dashed"></div>

        {{-- Data Kendaraan --}}
        <div style="font-size: 11.5px;">
            <div class="d-flex justify-content-between">
                <span>Unit</span>
                <span class="fw-bold">{{ $booking->vehicle_model }} {{ $booking->vehicle_year ? '('.$booking->vehicle_year.')' : '' }}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Plat Nomor</span>
                <span class="fw-bold">{{ $booking->plate_number }}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>KM Odometer</span>
                <span>{{ $booking->mileage ? number_format($booking->mileage, 0, ',', '.').' km' : '—' }}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Layanan</span>
                <span class="fw-bold">{{ $booking->service->name }}</span>
            </div>
        </div>

        @if ($booking->complaint)
            <div class="receipt-dashed"></div>
            <div style="font-size: 11px;">
                <span class="text-decoration-underline fw-bold">Keluhan Customer:</span>
                <div>{{ $booking->complaint }}</div>
            </div>
        @endif

        <div class="receipt-dashed"></div>

        {{-- Checklist Pekerjaan Mekanik --}}
        <div class="fw-bold text-center mb-1 text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
            -- CHECKLIST PEKERJAAN --
        </div>
        @forelse ($booking->tasks as $index => $task)
            <div class="mb-2" style="font-size: 11.5px;">
                <div class="fw-bold">{{ $index + 1 }}. {{ $task->name }}</div>
                <div class="d-flex justify-content-between text-muted" style="font-size: 10.5px; padding-left: 14px;">
                    <span>
                        <span class="receipt-check-box">@if ($task->isDone()) ✓ @elseif ($task->isSkipped()) - @endif</span>
                        @if ($task->isDone())
                            SELESAI
                        @elseif ($task->isSkipped())
                            DILEWATI
                        @else
                            BELUM
                        @endif
                    </span>
                    <span>Catatan: {{ $task->note ?: '............' }}</span>
                </div>
            </div>
        @empty
            <div class="text-center text-muted small">(Belum ada checklist pekerjaan)</div>
        @endforelse

        <div class="receipt-dashed"></div>

        {{-- Suku Cadang / Spare Part Diganti --}}
        <div class="fw-bold text-center mb-1 text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
            -- SUKU CADANG / PART --
        </div>
        @php($partsTotal = $booking->sparePartsTotal())
        @forelse ($booking->spareParts as $index => $item)
            <div class="mb-2" style="font-size: 11.5px;">
                <div class="fw-bold">{{ $index + 1 }}. {{ $item->part_name }}</div>
                <div class="d-flex justify-content-between" style="padding-left: 14px;">
                    <span>{{ $item->quantity }} x {{ number_format($item->price, 0, ',', '.') }}</span>
                    <span class="fw-bold">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                </div>
                @if ($item->condition_note)
                    <div class="text-muted" style="font-size: 10.5px; padding-left: 14px;">
                        Ket: {{ $item->condition_note }}
                    </div>
                @endif
            </div>
        @empty
            <div class="text-center text-muted small">(Belum ada part dicatat)</div>
            <div class="small text-muted mt-1" style="font-size: 10.5px;">Part rusak tambahan: .......................</div>
        @endforelse

        <div class="receipt-dashed"></div>

        {{-- Total & Biaya --}}
        <div style="font-size: 11.5px;">
            <div class="d-flex justify-content-between">
                <span>Total QTY Part</span>
                <span>: {{ $booking->spareParts->sum('quantity') }}</span>
            </div>
            @if ($booking->spareParts->isNotEmpty())
                <div class="d-flex justify-content-between">
                    <span>Sub Total Part</span>
                    <span>Rp {{ number_format($partsTotal, 0, ',', '.') }}</span>
                </div>
            @endif
            @if ($booking->service_price || $booking->service->price_from)
                <div class="d-flex justify-content-between">
                    <span>Jasa Servis</span>
                    <span>Rp {{ number_format($booking->service_price ?: $booking->service->price_from, 0, ',', '.') }}</span>
                </div>
            @endif
            <div class="receipt-dashed"></div>
            <div class="d-flex justify-content-between fw-bold fs-6">
                <span>TOTAL</span>
                <span>Rp {{ number_format(($booking->service_price ?: $booking->service->price_from) + $partsTotal, 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="receipt-dashed"></div>

        {{-- Kolom Tanda Tangan Kasir / Mekanik --}}
        <div class="d-flex justify-content-between text-center pt-2 mb-2" style="font-size: 11px;">
            <div style="width: 45%;">
                <div>Mekanik,</div>
                <div style="height: 36px;"></div>
                <div class="fw-bold border-top pt-1 text-truncate">( {{ $booking->mechanic?->name ?? '........' }} )</div>
            </div>
            <div style="width: 45%;">
                <div>Admin / SA,</div>
                <div style="height: 36px;"></div>
                <div class="fw-bold border-top pt-1 text-truncate">( {{ auth()->user()->name ?? 'Admin' }} )</div>
            </div>
        </div>

        <div class="receipt-dashed"></div>

        {{-- Footer Struk Kasir --}}
        <div class="text-center mt-2" style="font-size: 11px;">
            <div class="fw-bold">*** STRUK KERJA MEKANIK ***</div>
            <div class="text-muted" style="font-size: 10px; line-height: 1.3;">
                Mekanik checklist pekerjaan pada struk ini,<br>
                lalu berikan ke admin untuk update sistem.
            </div>
            <div class="mt-2 fw-medium">Terimakasih Telah Berbelanja</div>
        </div>
    </div>
</body>
</html>
