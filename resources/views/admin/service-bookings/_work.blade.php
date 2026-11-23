{{--
    Mekanik & checklist pekerjaan pada detail booking servis (admin).
    Butuh: $booking (dengan relasi mechanic, tasks, service), $mechanics ([id => nama] mekanik aktif).
--}}
@use('App\Models\ServiceBooking')
@use('App\Models\ServiceBookingTask')

@php
    [$handled, $total] = $booking->taskProgress();
    $mechanicErrors = $errors->getBag('mechanic');
    $taskErrors = $errors->getBag('task');
    $canManageChecklist = in_array($booking->status, [ServiceBooking::STATUS_IN_PROGRESS, ServiceBooking::STATUS_COMPLETED], true);
@endphp

<div class="card mb-4" id="pekerjaan">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h3 class="admin-card-title mb-0">Mekanik & Pekerjaan</h3>
                @if ($total > 0)
                    <span class="small text-muted">{{ $handled }} dari {{ $total }} pekerjaan ditangani</span>
                @endif
            </div>
            <a href="{{ route('admin.service-bookings.print-work-order', $booking) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Nota Checklist Servis">
                <i class="bi bi-printer me-1"></i>Cetak Nota Checklist
            </a>
        </div>

        @if ($booking->canBeAssigned())
            <form method="POST" action="{{ route('admin.service-bookings.assign-mechanic', $booking) }}" class="d-flex flex-wrap gap-2 align-items-start mb-3" novalidate>
                @csrf
                @method('PATCH')
                <div class="flex-grow-1">
                    <label for="mechanic_id" class="visually-hidden">Mekanik</label>
                    <select id="mechanic_id" name="mechanic_id" @class(['form-select', 'is-invalid' => $mechanicErrors->has('mechanic_id')])>
                        <option value="">{{ $mechanics === [] ? 'Belum ada akun mekanik aktif' : 'Pilih mekanik' }}</option>
                        @foreach ($mechanics as $id => $item)
                            @php
                                $label = is_array($item) ? ($item['label'] ?? $item['name'] ?? '') : $item;
                                $disabled = is_array($item) ? ($item['disabled'] ?? false) : false;
                            @endphp
                            <option value="{{ $id }}" @selected((int) old('mechanic_id', $booking->mechanic_id) === $id) @disabled($disabled)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($mechanicErrors->has('mechanic_id'))
                        <div class="invalid-feedback">{{ $mechanicErrors->first('mechanic_id') }}</div>
                    @endif
                </div>
                <button type="submit" class="btn btn-primary" @disabled($mechanics === [])>
                    <i class="bi bi-person-check"></i>{{ $booking->mechanic_id ? 'Ganti Mekanik' : 'Tugaskan' }}
                </button>
            </form>
        @endif

        <p class="small mb-3">
            <span class="text-muted">Mekanik:</span>
            @if ($booking->mechanic)
                <span class="fw-semibold">{{ $booking->mechanic->name }}</span>
                @unless ($booking->mechanic->isActive())
                    <span class="text-muted">(nonaktif)</span>
                @endunless
                @if ($booking->mechanic_chosen_by_customer)
                    <span class="badge rounded-pill text-bg-light border ms-1">Dipilih customer</span>
                @endif
            @else
                <span class="text-muted">belum ditugaskan</span>
            @endif
            @if ($booking->started_at)
                <span class="text-muted ms-2">Mulai {{ $booking->started_at->translatedFormat('d M H:i') }}</span>
            @endif
            @if ($booking->finished_at)
                <span class="text-muted ms-2">Selesai {{ $booking->finished_at->translatedFormat('d M H:i') }}</span>
            @endif
            @if ($booking->mechanic_id)
                <a href="{{ route('admin.service-bookings.print-work-order', $booking) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 ms-2" title="Cetak SPK / Struk Kerja Mekanik">
                    <i class="bi bi-printer me-1"></i>Cetak SPK
                </a>
            @endif
        </p>

        @if ($booking->tasks->isEmpty())
            <p class="small text-muted mb-3">
                @if ($booking->mechanic_id)
                    Checklist masih kosong. Tambahkan pekerjaan di bawah, atau isi Daftar Pekerjaan Mekanik di menu Layanan Servis.
                @else
                    Checklist dari layanan {{ $booking->service->name }} disalin saat mekanik ditugaskan.
                @endif
            </p>
        @else
            @if ($booking->status === ServiceBooking::STATUS_CONFIRMED)
                <div class="alert alert-light border small mb-3">
                    <i class="bi bi-info-circle me-1 text-primary"></i>Checklist pekerjaan baru dapat dicentang setelah status servis diubah ke <strong>Sedang Dikerjakan</strong>.
                </div>
            @endif

            <ul class="service-task-list list-unstyled small mb-3">
                @foreach ($booking->tasks as $task)
                    <li @class(['is-done' => $task->isDone(), 'is-skipped' => $task->isSkipped(), 'align-items-center' => true])>
                        <i @class([
                            'bi fs-5',
                            'bi-check-circle-fill text-success' => $task->isDone(),
                            'bi-dash-circle text-muted' => $task->isSkipped(),
                            'bi-circle text-muted' => $task->isPending(),
                        ]) aria-hidden="true"></i>

                        <div class="flex-grow-1 min-w-0">
                            <span class="service-task-name fw-medium">{{ $task->name }}</span>
                            @if ($task->isDone() && $task->checked_at)
                                <span class="small text-muted ms-1">· selesai {{ $task->checked_at->translatedFormat('H:i') }}</span>
                            @elseif ($task->isSkipped() && $task->note)
                                <div class="text-muted small">Dilewati: {{ $task->note }}</div>
                            @endif
                        </div>

                        @if ($canManageChecklist)
                            <div class="d-flex align-items-center gap-1 ms-auto">
                                @if ($task->isPending())
                                    <form method="POST" action="{{ route('admin.service-bookings.tasks.update', [$booking, $task]) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ ServiceBookingTask::STATUS_DONE }}">
                                        <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2" title="Tandai selesai">
                                            <i class="bi bi-check-lg"></i> Selesai
                                        </button>
                                    </form>

                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#admin-task-skip-{{ $task->id }}" title="Lewati dengan alasan">
                                        Lewati
                                    </button>

                                    @if ($booking->canBeAssigned())
                                        <form method="POST" action="{{ route('admin.service-bookings.tasks.destroy', [$booking, $task]) }}"
                                              data-confirm="Hapus pekerjaan &quot;{{ $task->name }}&quot; dari checklist?" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0 ms-1" aria-label="Hapus {{ $task->name }}" title="Hapus pekerjaan">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('admin.service-bookings.tasks.update', [$booking, $task]) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ ServiceBookingTask::STATUS_PENDING }}">
                                        <button type="submit" class="btn btn-sm btn-link text-muted p-0 small text-decoration-none" title="Ubah kembali ke belum selesai">
                                            <i class="bi bi-arrow-counterclockwise"></i> Batal centang
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </li>

                    @if ($canManageChecklist && $task->isPending())
                        <li class="collapse p-0 border-0" id="admin-task-skip-{{ $task->id }}">
                            <form method="POST" action="{{ route('admin.service-bookings.tasks.update', [$booking, $task]) }}" class="d-flex gap-2 p-2 bg-light rounded-2 mb-2 w-100">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ ServiceBookingTask::STATUS_SKIPPED }}">
                                <input type="text" name="note" class="form-control form-control-sm" placeholder="Alasan dilewati (mis. part belum tiba)">
                                <button type="submit" class="btn btn-sm btn-secondary">Simpan</button>
                            </form>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif

        @if ($booking->canBeAssigned() && $booking->mechanic_id)
            <form method="POST" action="{{ route('admin.service-bookings.tasks.store', $booking) }}" class="d-flex gap-2 align-items-start mb-3" novalidate>
                @csrf
                <div class="flex-grow-1">
                    <label for="task-name" class="visually-hidden">Pekerjaan tambahan</label>
                    <input type="text" id="task-name" name="name" maxlength="{{ ServiceBookingTask::MAX_NAME_LENGTH }}"
                           value="{{ $taskErrors->any() ? old('name') : '' }}" placeholder="Pekerjaan tambahan, mis. cek bunyi roda depan"
                           @class(['form-control form-control-sm', 'is-invalid' => $taskErrors->has('name')])>
                    @if ($taskErrors->has('name'))
                        <div class="invalid-feedback">{{ $taskErrors->first('name') }}</div>
                    @endif
                </div>
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg"></i>Tambah</button>
            </form>
        @endif

        {{-- Form Temuan & Rekomendasi Teknis --}}
        <div class="mt-4 pt-3 border-top">
            <h4 class="h6 fw-bold mb-1">Temuan & Rekomendasi Teknis</h4>
            <p class="small text-muted mb-2">Catatan kondisi kendaraan atau rekomendasi perbaikan untuk customer (tampil di akun customer menu Servis Saya).</p>
            <form method="POST" action="{{ route('admin.service-bookings.findings', $booking) }}">
                @csrf
                @method('PATCH')
                <div class="mb-2">
                    <textarea name="findings" rows="3" maxlength="{{ ServiceBooking::MAX_FINDINGS_LENGTH }}"
                              placeholder="Mis. kampas rem depan tinggal 20%, disarankan penggantian pada servis berikutnya."
                              class="form-control form-control-sm">{{ old('findings', $booking->findings) }}</textarea>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-save me-1"></i>Simpan Temuan & Rekomendasi
                </button>
            </form>
        </div>

        @if ($booking->mechanicRating)
            <div class="small mt-3 pt-3 border-top">
                <span class="fw-semibold">Rating customer untuk mekanik:</span>
                <x-rating-stars :rating="$booking->mechanicRating->rating" class="text-warning ms-1" />
                @if ($booking->mechanicRating->comment)
                    <div class="text-muted mt-1">“{{ $booking->mechanicRating->comment }}”</div>
                @endif
            </div>
        @endif
    </div>
</div>
