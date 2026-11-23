{{--
    Panel Suku Cadang / Spare Part pada detail booking servis (admin).
    Butuh: $booking (dengan relasi spareParts), $spareParts (koleksi SparePart aktif dengan stok > 0).
--}}
@php
    $sparePartErrors = $errors->getBag('sparePart');
    $canManageParts = $booking->canBeAssigned();
    $partsTotal = $booking->sparePartsTotal();
    $grandTotal = $booking->grandTotal();
@endphp

<div class="card mb-4" id="spare-parts">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
            <div>
                <h3 class="admin-card-title mb-0">Suku Cadang & Penggantian Part Rusak</h3>
                <p class="small text-muted mb-0">Catat suku cadang yang diganti untuk kendaraan customer. Stok katalog terpotong otomatis.</p>
            </div>
            @if ($booking->spareParts->isNotEmpty())
                <span class="badge text-bg-primary fs-6">{{ $booking->spareParts->count() }} Item Part</span>
            @endif
        </div>

        @if ($booking->spareParts->isEmpty())
            <div class="alert alert-light border small text-muted mb-3">
                <i class="bi bi-info-circle me-1"></i>Belum ada suku cadang yang dicatat untuk kendaraan ini. Jika ada komponen yang rusak/diganti saat servis, tambahkan melalui form di bawah.
            </div>
        @else
            <div class="table-responsive mb-3">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th style="width: 34%;">Suku Cadang</th>
                            <th style="width: 26%;">Catatan Kerusakan</th>
                            <th class="text-center" style="width: 10%;">Qty</th>
                            <th class="text-end" style="width: 14%;">Harga Satuan</th>
                            <th class="text-end" style="width: 12%;">Subtotal</th>
                            @if ($canManageParts)
                                <th class="text-end" style="width: 5%;"><span class="visually-hidden">Aksi</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="small">
                        @foreach ($booking->spareParts as $index => $item)
                            <tr>
                                <td class="text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $item->part_name }}</div>
                                    @if ($item->part_number)
                                        <div class="text-muted font-monospace" style="font-size: 0.75rem;">No: {{ $item->part_number }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->condition_note)
                                        <span class="text-danger-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>{{ $item->condition_note }}</span>
                                    @else
                                        <span class="text-muted fst-italic">Penggantian berkala / aus</span>
                                    @endif
                                </td>
                                <td class="text-center fw-bold">{{ $item->quantity }}</td>
                                <td class="text-end">{{ $item->formattedPrice() }}</td>
                                <td class="text-end fw-semibold">{{ $item->formattedSubtotal() }}</td>
                                @if ($canManageParts)
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.service-bookings.spare-parts.destroy', [$booking, $item]) }}"
                                              data-confirm="Hapus {{ $item->part_name }} dari servis ini? Stok akan dikembalikan." class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Hapus part">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-group-divider small">
                        <tr>
                            <td colspan="{{ $canManageParts ? 5 : 4 }}" class="text-end fw-bold">Total Spare Part:</td>
                            <td class="text-end fw-bold text-primary">Rp {{ number_format($partsTotal, 0, ',', '.') }}</td>
                            @if ($canManageParts)<td></td>@endif
                        </tr>
                        @if ($booking->service_price || $booking->service->price_from)
                            <tr>
                                <td colspan="{{ $canManageParts ? 5 : 4 }}" class="text-end text-muted">
                                    Jasa Servis ({{ $booking->service->name }}):
                                </td>
                                <td class="text-end text-muted">
                                    Rp {{ number_format($booking->service_price ?: $booking->service->price_from, 0, ',', '.') }}
                                </td>
                                @if ($canManageParts)<td></td>@endif
                            </tr>
                            <tr class="fw-bold fs-6">
                                <td colspan="{{ $canManageParts ? 5 : 4 }}" class="text-end">Estimasi Total Servis:</td>
                                <td class="text-end text-success">
                                    Rp {{ number_format(($booking->service_price ?: $booking->service->price_from) + $partsTotal, 0, ',', '.') }}
                                </td>
                                @if ($canManageParts)<td></td>@endif
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        @endif

        {{-- Form Tambah Spare Part --}}
        @if ($canManageParts)
            <div class="mt-3 pt-3 border-top">
                <h4 class="h6 fw-bold mb-3"><i class="bi bi-plus-circle me-1"></i>Tambah Suku Cadang Pengganti</h4>
                <form method="POST" action="{{ route('admin.service-bookings.spare-parts.store', $booking) }}" novalidate>
                    @csrf
                    <div class="row g-3">
                        {{-- Baris 1: Suku Cadang & Jumlah Qty --}}
                        <div class="col-md-9 col-sm-8">
                            <label for="part-id" class="form-label small mb-1 fw-medium">Pilih Suku Cadang (Katalog)</label>
                            <select id="part-id" name="spare_part_id" required
                                    @class(['form-select form-select-sm', 'is-invalid' => $sparePartErrors->has('spare_part_id')])>
                                <option value="">{{ $spareParts->isEmpty() ? 'Tidak ada stok suku cadang' : 'Pilih suku cadang' }}</option>
                                @foreach ($spareParts as $part)
                                    <option value="{{ $part->id }}" @selected(old('spare_part_id') == $part->id)>
                                        {{ $part->name }} [{{ $part->part_number }}] - Rp {{ number_format($part->price, 0, ',', '.') }} (Stok: {{ $part->stock }} {{ $part->unit }})
                                    </option>
                                @endforeach
                            </select>
                            @if ($sparePartErrors->has('spare_part_id'))
                                <div class="invalid-feedback">{{ $sparePartErrors->first('spare_part_id') }}</div>
                            @endif
                        </div>

                        <div class="col-md-3 col-sm-4">
                            <label for="part-qty" class="form-label small mb-1 fw-medium">Jumlah (Qty)</label>
                            <input type="number" id="part-qty" name="quantity" min="1" value="{{ old('quantity', 1) }}" required
                                   @class(['form-control form-control-sm', 'is-invalid' => $sparePartErrors->has('quantity')])>
                            @if ($sparePartErrors->has('quantity'))
                                <div class="invalid-feedback">{{ $sparePartErrors->first('quantity') }}</div>
                            @endif
                        </div>

                        {{-- Baris 2: Catatan Kerusakan & Tombol Tambah --}}
                        <div class="col-md-9 col-sm-8">
                            <label for="part-note" class="form-label small mb-1 fw-medium">Catatan Kerusakan Kendaraan</label>
                            <input type="text" id="part-note" name="condition_note" value="{{ old('condition_note') }}"
                                   placeholder="Mis. kampas aus tipis, bocor oli, retak"
                                   maxlength="{{ \App\Models\ServiceBookingSparePart::MAX_NOTE_LENGTH }}"
                                   @class(['form-control form-control-sm', 'is-invalid' => $sparePartErrors->has('condition_note')])>
                            @if ($sparePartErrors->has('condition_note'))
                                <div class="invalid-feedback">{{ $sparePartErrors->first('condition_note') }}</div>
                            @endif
                        </div>

                        <div class="col-md-3 col-sm-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-sm btn-primary w-100 text-nowrap" @disabled($spareParts->isEmpty())>
                                <i class="bi bi-cart-plus me-1"></i>Tambah
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
