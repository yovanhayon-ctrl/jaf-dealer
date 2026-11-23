<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ChangeServiceBookingStatus;
use App\Actions\RescheduleBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RescheduleRequest;
use App\Http\Requests\Admin\ServiceBookingStatusRequest;
use App\Models\ActivityLog;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceBookingSparePart;
use App\Models\ServiceBookingTask;
use App\Models\SparePart;
use App\Models\User;
use App\Notifications\MechanicJobAssigned;
use App\Notifications\Notifier;
use DateTime;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Kelola booking servis: lihat, ubah status + catatan, tugaskan mekanik & atur checklist. Data dibuat customer di halaman publik.
 */
class ServiceBookingController extends Controller
{
    /** Pilihan urutan: nilai query string => [[kolom, arah], ...]. */
    public const SORTS = [
        'terbaru' => [['created_at', 'desc']],
        'jadwal' => [['preferred_date', 'asc'], ['preferred_time', 'asc']],
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $bookings = ServiceBooking::query()
            ->with(['user:id,name,email', 'service:id,name', 'mechanic:id,name'])
            ->tap(fn (Builder $query) => $this->applyFilters($query, $filters))
            ->tap(function (Builder $query) use ($filters) {
                foreach (self::SORTS[$filters['urut']] as [$column, $direction]) {
                    $query->orderBy($column, $direction);
                }
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.service-bookings.index', [
            'bookings' => $bookings,
            'filters' => $filters,
            'serviceOptions' => Service::orderBy('name')->pluck('name', 'id')->all(),
            'hasFilters' => collect($filters)->except('urut')->filter(fn ($value) => $value !== null)->isNotEmpty(),
        ]);
    }

    public function show(ServiceBooking $serviceBooking): View
    {
        $serviceBooking->load([
            'user:id,name,email,phone',
            'service:id,name,price_from,duration_minutes,is_active,checklist',
            'mechanic:id,name,is_active',
            'tasks',
            'spareParts.sparePart',
            'mechanicRating',
        ]);

        $activeBookings = ServiceBooking::query()
            ->active()
            ->whereNotNull('mechanic_id')
            ->where('id', '!=', $serviceBooking->id)
            ->get(['id', 'mechanic_id', 'status', 'preferred_date', 'preferred_time', 'plate_number']);

        $activeByMechanic = $activeBookings->groupBy('mechanic_id');
        $targetDateStr = $serviceBooking->preferred_date?->toDateString();
        $targetTimeStr = substr((string) $serviceBooking->preferred_time, 0, 5);

        $mechanics = User::activeMechanics()
            ->get(['id', 'name'])
            ->mapWithKeys(function ($m) use ($activeByMechanic, $targetDateStr, $targetTimeStr) {
                /** @var \Illuminate\Support\Collection<int, ServiceBooking> $mBookings */
                $mBookings = $activeByMechanic->get($m->id, collect());

                // 1. Cek bentrok jadwal pada tanggal & jam yang sama persis
                $conflict = $mBookings->first(function ($b) use ($targetDateStr, $targetTimeStr) {
                    return $b->preferred_date?->toDateString() === $targetDateStr
                        && substr((string) $b->preferred_time, 0, 5) === $targetTimeStr;
                });

                if ($conflict) {
                    return [$m->id => [
                        'name' => $m->name,
                        'label' => "{$m->name} (Bentrok jadwal jam ini)",
                        'disabled' => true,
                    ]];
                }

                // 2. Cek apakah sedang mengerjakan servis lain (status in_progress)
                $inProgress = $mBookings->firstWhere('status', ServiceBooking::STATUS_IN_PROGRESS);
                if ($inProgress) {
                    return [$m->id => [
                        'name' => $m->name,
                        'label' => "{$m->name} (Sedang mengerjakan: {$inProgress->plate_number})",
                        'disabled' => false,
                    ]];
                }

                // 3. Mekanik luang / tersedia
                return [$m->id => [
                    'name' => $m->name,
                    'label' => "{$m->name} (Tersedia)",
                    'disabled' => false,
                ]];
            })
            ->all();

        return view('admin.service-bookings.show', [
            'booking' => $serviceBooking,
            'mechanics' => $mechanics,
            'spareParts' => SparePart::query()->active()->where('stock', '>', 0)->orderBy('name')->get(['id', 'part_number', 'name', 'price', 'stock', 'unit']),
        ]);
    }

    public function updateStatus(
        ServiceBookingStatusRequest $request,
        ServiceBooking $serviceBooking,
        ChangeServiceBookingStatus $changeStatus,
    ): RedirectResponse {
        $oldLabel = $serviceBooking->statusLabel();
        $status = $request->validated('status');
        $redirect = redirect()->route('admin.service-bookings.show', $serviceBooking);

        try {
            $updated = $changeStatus->handle($serviceBooking, $status, ['admin_note' => $request->validated('admin_note')]);
        } catch (DomainException $e) {
            return $redirect->withInput()->with('error', $e->getMessage());
        }

        if ($status === null) {
            return $redirect->with('success', 'Catatan admin booking servis berhasil disimpan.');
        }

        if (! $updated->wasChanged('status')) {
            return $redirect->with('status', "Booking servis ini sudah berstatus {$updated->statusLabel()}. Tidak ada perubahan. Catatan Anda tidak disimpan.");
        }

        return $redirect->with('success', "Status booking servis diubah dari {$oldLabel} menjadi {$updated->statusLabel()}.");
    }

    /**
     * Tugaskan (atau ganti) mekanik. Checklist layanan disalin saat pertama kali ditugaskan.
     */
    public function assignMechanic(Request $request, ServiceBooking $serviceBooking): RedirectResponse
    {
        $validated = $request->validateWithBag('mechanic', [
            'mechanic_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_MECHANIC)->where('is_active', true)],
        ], ['mechanic_id.required' => 'Pilih mekanik.', 'mechanic_id.exists' => 'Mekanik tidak ditemukan atau sudah nonaktif.']);

        $redirect = $this->backToWork($serviceBooking);

        if (! $serviceBooking->canBeAssigned()) {
            return $redirect->with('error', 'Mekanik hanya bisa ditugaskan pada booking yang masih berjalan.');
        }

        $mechanic = User::findOrFail($validated['mechanic_id']);

        // Anti-bentrok jadwal: tolak jika mekanik sudah memiliki booking aktif lain pada tanggal & jam yang sama persis
        $targetDateStr = $serviceBooking->preferred_date?->toDateString();
        $targetTimeStr = substr((string) $serviceBooking->preferred_time, 0, 5);

        $conflict = ServiceBooking::query()
            ->active()
            ->where('mechanic_id', $mechanic->id)
            ->where('id', '!=', $serviceBooking->id)
            ->whereDate('preferred_date', $targetDateStr)
            ->whereTime('preferred_time', '=', "{$targetTimeStr}:00")
            ->first(['id', 'plate_number', 'preferred_date', 'preferred_time']);

        if ($conflict) {
            $formattedDate = $serviceBooking->preferred_date?->translatedFormat('d M Y') ?? $targetDateStr;
            $formattedTime = $serviceBooking->timeLabel();

            return $redirect->withInput()->withErrors([
                'mechanic_id' => "Mekanik {$mechanic->name} sudah memiliki jadwal servis aktif ({$conflict->plate_number}) pada {$formattedDate} pukul {$formattedTime} WIB.",
            ], 'mechanic');
        }

        $reassigned = $serviceBooking->mechanic_id !== $mechanic->id;
        $serviceBooking->update([
            'mechanic_id' => $mechanic->id,
            // Diganti admin: bukan lagi pilihan customer.
            'mechanic_chosen_by_customer' => $serviceBooking->mechanic_chosen_by_customer && $serviceBooking->mechanic_id === $mechanic->id,
        ]);
        $serviceBooking->copyChecklistFromService();

        // Booking yang sudah dikonfirmasi langsung muncul di Pekerjaan Saya; yang masih menunggu dikabarkan saat dikonfirmasi.
        if ($reassigned && in_array($serviceBooking->status, [ServiceBooking::STATUS_CONFIRMED, ServiceBooking::STATUS_IN_PROGRESS], true)) {
            Notifier::send($mechanic, new MechanicJobAssigned($serviceBooking));
        }

        return $redirect->with('success', "Booking ditugaskan ke {$mechanic->name}. Checklist pekerjaan siap dikerjakan.");
    }

    /**
     * Tambah pekerjaan di luar daftar bawaan (mis. dari keluhan customer).
     */
    public function addTask(Request $request, ServiceBooking $serviceBooking): RedirectResponse
    {
        $validated = $request->validateWithBag('task', [
            'name' => ['required', 'string', 'min:3', 'max:'.ServiceBookingTask::MAX_NAME_LENGTH],
        ], [], ['name' => 'nama pekerjaan']);

        $redirect = $this->backToWork($serviceBooking);

        if (! $serviceBooking->canBeAssigned()) {
            return $redirect->with('error', 'Checklist tidak bisa diubah setelah booking selesai atau dibatalkan.');
        }

        $serviceBooking->tasks()->create([
            'name' => Str::squish($validated['name']),
            'position' => (int) $serviceBooking->tasks()->max('position') + 1,
        ]);

        return $redirect->with('success', 'Pekerjaan ditambahkan ke checklist.');
    }

    /**
     * Hapus pekerjaan yang belum dikerjakan.
     */
    public function removeTask(ServiceBooking $serviceBooking, ServiceBookingTask $task): RedirectResponse
    {
        $redirect = $this->backToWork($serviceBooking);

        if (! $serviceBooking->canBeAssigned() || ! $task->isPending()) {
            return $redirect->with('error', 'Hanya pekerjaan yang belum dikerjakan yang bisa dihapus.');
        }

        $task->delete();

        return $redirect->with('success', "Pekerjaan \"{$task->name}\" dihapus dari checklist.");
    }

    /**
     * Admin centang / ubah status butir pekerjaan checklist (selesai, dilewati, belum).
     */
    public function updateTask(Request $request, ServiceBooking $serviceBooking, ServiceBookingTask $task): RedirectResponse
    {
        $validated = $request->validateWithBag("task{$task->id}", [
            'status' => ['required', Rule::in(ServiceBookingTask::STATUSES)],
            'note' => ['nullable', 'string', 'max:'.ServiceBookingTask::MAX_NOTE_LENGTH],
        ], [], ['note' => 'alasan']);

        $redirect = $this->backToWork($serviceBooking);

        if (! in_array($serviceBooking->status, [ServiceBooking::STATUS_IN_PROGRESS, ServiceBooking::STATUS_COMPLETED], true)) {
            return $redirect->with('error', 'Checklist pekerjaan baru dapat dicentang saat servis sedang dikerjakan.');
        }

        $task->update([
            'status' => $validated['status'],
            'note' => $validated['status'] === ServiceBookingTask::STATUS_SKIPPED ? ($validated['note'] ?: 'Dilewati oleh admin') : null,
            'checked_at' => $validated['status'] === ServiceBookingTask::STATUS_PENDING ? null : now(),
        ]);

        return $redirect->with('success', "Status pekerjaan \"{$task->name}\" diperbarui.");
    }

    /**
     * Admin simpan / ubah temuan & rekomendasi teknis untuk customer.
     */
    public function updateFindings(Request $request, ServiceBooking $serviceBooking): RedirectResponse
    {
        $validated = $request->validateWithBag('findings', [
            'findings' => ['nullable', 'string', 'max:'.ServiceBooking::MAX_FINDINGS_LENGTH],
        ], [], ['findings' => 'temuan & rekomendasi']);

        $serviceBooking->update(['findings' => trim((string) $validated['findings']) ?: null]);

        return $this->backToWork($serviceBooking)->with('success', 'Temuan & rekomendasi disimpan.');
    }

    /**
     * Tambah suku cadang / spare part yang diganti karena rusak pada kendaraan customer.
     */
    public function addSparePart(Request $request, ServiceBooking $serviceBooking): RedirectResponse
    {
        $validated = $request->validateWithBag('sparePart', [
            'spare_part_id' => ['required', Rule::exists('spare_parts', 'id')->where('is_active', true)],
            'quantity' => ['required', 'integer', 'min:1'],
            'condition_note' => ['nullable', 'string', 'max:'.ServiceBookingSparePart::MAX_NOTE_LENGTH],
        ], [
            'spare_part_id.required' => 'Pilih suku cadang.',
            'spare_part_id.exists' => 'Suku cadang tidak ditemukan atau tidak aktif.',
            'quantity.required' => 'Jumlah suku cadang wajib diisi.',
            'quantity.min' => 'Jumlah suku cadang minimal 1.',
        ], ['condition_note' => 'catatan kerusakan']);

        $redirect = redirect()->to(route('admin.service-bookings.show', $serviceBooking).'#spare-parts');

        if (! $serviceBooking->canBeAssigned()) {
            return $redirect->with('error', 'Suku cadang tidak bisa diubah setelah booking selesai atau dibatalkan.');
        }

        try {
            $sparePart = DB::transaction(function () use ($serviceBooking, $validated) {
                $sparePart = SparePart::whereKey($validated['spare_part_id'])->lockForUpdate()->firstOrFail();
                $qty = (int) $validated['quantity'];

                if ($sparePart->stock < $qty) {
                    throw ValidationException::withMessages([
                        'quantity' => "Stok tidak mencukupi (sisa {$sparePart->stock} {$sparePart->unit}).",
                    ]);
                }

                $sparePart->decrement('stock', $qty);

                $serviceBooking->spareParts()->create([
                    'spare_part_id' => $sparePart->id,
                    'part_name' => $sparePart->name,
                    'part_number' => $sparePart->part_number,
                    'quantity' => $qty,
                    'price' => $sparePart->price,
                    'subtotal' => $sparePart->price * $qty,
                    'condition_note' => trim((string) ($validated['condition_note'] ?? '')) ?: null,
                ]);

                return $sparePart;
            });
        } catch (ValidationException $e) {
            return $redirect->withErrors($e->errors(), 'sparePart');
        }

        ActivityLog::record(
            ActivityLog::CATEGORY_FINANCE,
            'service_booking.add_spare_part',
            "Menambahkan {$validated['quantity']} {$sparePart->unit} \"{$sparePart->name}\" (".ActivityLog::rupiah($sparePart->price * (int) $validated['quantity']).") ke booking servis #{$serviceBooking->id}",
            $serviceBooking,
            ['spare_part_id' => $sparePart->id, 'quantity' => (int) $validated['quantity'], 'subtotal' => $sparePart->price * (int) $validated['quantity']]
        );

        return $redirect->with('success', "Suku cadang \"{$sparePart->name}\" berhasil ditambahkan.");
    }

    /**
     * Hapus suku cadang dari booking servis dan kembalikan stok.
     */
    public function removeSparePart(ServiceBooking $serviceBooking, ServiceBookingSparePart $sparePart): RedirectResponse
    {
        $redirect = redirect()->to(route('admin.service-bookings.show', $serviceBooking).'#spare-parts');

        if (! $serviceBooking->canBeAssigned()) {
            return $redirect->with('error', 'Suku cadang tidak bisa diubah setelah booking selesai atau dibatalkan.');
        }

        DB::transaction(function () use ($sparePart) {
            if ($sparePart->spare_part_id && ($masterPart = SparePart::whereKey($sparePart->spare_part_id)->lockForUpdate()->first())) {
                $masterPart->increment('stock', $sparePart->quantity);
            }

            $sparePart->delete();
        });

        ActivityLog::record(
            ActivityLog::CATEGORY_FINANCE,
            'service_booking.remove_spare_part',
            "Menghapus {$sparePart->quantity} unit \"{$sparePart->part_name}\" dari booking servis #{$serviceBooking->id} dan mengembalikan stok",
            $serviceBooking,
            ['spare_part_id' => $sparePart->spare_part_id, 'quantity' => $sparePart->quantity, 'subtotal' => $sparePart->subtotal]
        );

        return $redirect->with('success', "Suku cadang \"{$sparePart->part_name}\" dihapus dan stok dikembalikan.");
    }

    /**
     * Tampilan cetak Surat Perintah Kerja (SPK) / Struk Kerja Mekanik.
     */
    public function printWorkOrder(ServiceBooking $serviceBooking): View
    {
        $serviceBooking->load([
            'user:id,name,email,phone',
            'service:id,name,price_from,duration_minutes',
            'mechanic:id,name',
            'tasks',
            'spareParts',
        ]);

        return view('admin.service-bookings.print', [
            'booking' => $serviceBooking,
        ]);
    }

    /**
     * Jadwal ulang (status menjadi Dikonfirmasi, customer diberi tahu). Lihat RescheduleBooking.
     */
    public function reschedule(RescheduleRequest $request, ServiceBooking $serviceBooking, RescheduleBooking $reschedule): RedirectResponse
    {
        $redirect = redirect()->route('admin.service-bookings.show', $serviceBooking);

        try {
            $updated = $reschedule->handle($serviceBooking, $request->validated('preferred_date'), $request->validated('preferred_time'));
        } catch (DomainException $e) {
            return $redirect->with('error', $e->getMessage());
        }

        return $redirect->with('success', 'Jadwal diubah ke '.$updated->preferred_date->translatedFormat('d M Y')." {$updated->timeLabel()} WIB. Customer sudah diberi tahu.");
    }

    private function backToWork(ServiceBooking $serviceBooking): RedirectResponse
    {
        return redirect()->to(route('admin.service-bookings.show', $serviceBooking).'#pekerjaan');
    }

    /**
     * Ambil filter dari query string; nilai yang tidak dikenal diabaikan.
     *
     * @return array{q: ?string, status: ?string, layanan: ?int, dari: ?string, sampai: ?string, urut: string}
     */
    private function filters(Request $request): array
    {
        $pick = fn (string $key, array $allowed) => in_array($request->query($key), $allowed, true) ? $request->query($key) : null;
        $date = function (string $key) use ($request): ?string {
            $value = (string) $request->query($key);
            $parsed = DateTime::createFromFormat('!Y-m-d', $value);

            return $parsed && $parsed->format('Y-m-d') === $value ? $value : null;
        };
        $serviceId = filter_var($request->query('layanan'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return [
            'q' => trim((string) $request->query('q')) ?: null,
            'status' => $pick('status', ServiceBooking::STATUSES),
            'layanan' => $serviceId === false ? null : $serviceId,
            'dari' => $date('dari'),
            'sampai' => $date('sampai'),
            // "lewat": masih Menunggu/Dikonfirmasi padahal tanggalnya sudah lewat.
            'jadwal' => $pick('jadwal', ['lewat']),
            'urut' => $pick('urut', array_keys(self::SORTS)) ?? 'terbaru',
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['q'], fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('plate_number', 'like', "%{$search}%")
                ->orWhere('vehicle_model', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['layanan'], fn ($q, $serviceId) => $q->where('service_id', $serviceId))
            ->when($filters['dari'], fn ($q, $date) => $q->whereDate('preferred_date', '>=', $date))
            ->when($filters['sampai'], fn ($q, $date) => $q->whereDate('preferred_date', '<=', $date))
            ->when($filters['jadwal'] === 'lewat', fn ($q) => $q->overdue());
    }
}
