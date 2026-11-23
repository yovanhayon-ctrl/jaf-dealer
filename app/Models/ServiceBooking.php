<?php

namespace App\Models;

use App\Models\Concerns\HasSchedule;
use Database\Factories\ServiceBookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id', 'service_id', 'service_price', 'vehicle_model', 'plate_number', 'vehicle_year', 'mileage',
    'preferred_date', 'preferred_time', 'phone', 'complaint', 'status', 'admin_note',
    'mechanic_id', 'mechanic_chosen_by_customer', 'findings', 'started_at', 'finished_at',
])]
class ServiceBooking extends Model
{
    /** @use HasFactory<ServiceBookingFactory> */
    use HasFactory;

    use HasSchedule;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Customer tidak hadir pada jadwal yang sudah dikonfirmasi (lihat HasSchedule). */
    public const STATUS_NO_SHOW = 'no_show';

    public const STATUSES = [
        self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_NO_SHOW,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu',
        self::STATUS_CONFIRMED => 'Dikonfirmasi',
        self::STATUS_IN_PROGRESS => 'Dikerjakan',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_CANCELLED => 'Dibatalkan',
        self::STATUS_NO_SHOW => 'Tidak datang',
    ];

    /** Transisi status yang diizinkan. Status lain adalah status akhir. Tidak datang: lihat allowedTransitions(). */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_IN_PROGRESS, self::STATUS_NO_SHOW, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED],
    ];

    /** Status yang wajib disertai catatan admin (alasan untuk customer). */
    public const NOTE_REQUIRED_STATUSES = [self::STATUS_CANCELLED];

    /** Tanda bagian catatan yang wajib dilengkapi admin (alasan membatalkan). */
    public const NOTE_PLACEHOLDER = '…';

    /**
     * Catatan admin otomatis saat status diubah (form admin mengisinya, admin boleh mengubah).
     * Dibatalkan berisi tanda "…" yang wajib diganti alasan sebelum disimpan.
     */
    public const NOTE_TEMPLATES = [
        self::STATUS_CONFIRMED => 'Booking servis Anda telah dikonfirmasi. Silakan datang ke bengkel tepat waktu sesuai jadwal.',
        self::STATUS_IN_PROGRESS => 'Kendaraan Anda sedang dalam proses pengerjaan oleh tim mekanik kami.',
        self::STATUS_COMPLETED => 'Pengerjaan servis telah selesai. Kendaraan Anda sudah siap untuk diambil di bengkel.',
        self::STATUS_CANCELLED => 'Mohon maaf, booking servis dibatalkan karena '.self::NOTE_PLACEHOLDER,
    ];

    /** Status yang masih memakai slot bengkel: dipakai cek kapasitas slot & booking ganda per plat. */
    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS];

    /** Customer hanya boleh membatalkan saat status ini. */
    public const CUSTOMER_CANCELLABLE_STATUSES = [self::STATUS_PENDING];

    /** Slot jam booking servis (WIB), per jam 08:00–15:00. */
    public const TIME_SLOTS = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00'];

    /** Jeda minimal pemesanan sebelum jam kedatangan servis (jam). */
    public const MIN_LEAD_HOURS = 1;

    /** Rentang tanggal booking: hari ini / besok s/d hari ini + 30 hari (WIB). */
    public const MIN_DAYS_AHEAD = 0;

    public const MAX_DAYS_AHEAD = 30;

    /**
     * Daftar slot jam yang masih dapat dipesan pada tanggal tertentu.
     * Untuk tanggal hari ini: hanya slot yang minimal berjarak MIN_LEAD_HOURS dari waktu sekarang.
     * Untuk tanggal masa depan: seluruh TIME_SLOTS.
     * Untuk tanggal masa lalu: array kosong [].
     *
     * @return array<int, string>
     */
    public static function availableTimeSlots(?string $date = null): array
    {
        $targetDate = $date ?? today()->toDateString();
        $today = today()->toDateString();

        if ($targetDate < $today) {
            return [];
        }

        if ($targetDate > $today) {
            return self::TIME_SLOTS;
        }

        $cutoff = now()->addHours(self::MIN_LEAD_HOURS);

        return array_values(array_filter(self::TIME_SLOTS, function (string $slot) use ($today, $cutoff) {
            $slotTime = Carbon::parse("{$today} {$slot}:00", config('app.timezone', 'Asia/Jakarta'));

            return $slotTime->gte($cutoff);
        }));
    }

    /**
     * Tanggal paling awal yang dapat dipesan pelanggan.
     * Jika hari ini masih memiliki slot jam yang valid (sebelum cutoff jam operasional), return hari ini.
     * Jika seluruh slot hari ini sudah habis/terlewat, return besok (H+1).
     */
    public static function firstAvailableDate(): string
    {
        return self::availableTimeSlots(today()->toDateString()) !== []
            ? today()->toDateString()
            : today()->addDay()->toDateString();
    }

    protected function casts(): array
    {
        return [
            'service_price' => 'integer',
            'preferred_date' => 'date',
            'reminded_at' => 'datetime',
            'vehicle_year' => 'integer',
            'mileage' => 'integer',
            'mechanic_chosen_by_customer' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Booking yang masih memakai slot bengkel (pending / confirmed / in_progress).
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Mekanik yang ditugaskan admin. */
    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mechanic_id');
    }

    /** Checklist pekerjaan booking ini, urut sesuai posisi. */
    public function tasks(): HasMany
    {
        return $this->hasMany(ServiceBookingTask::class)->orderBy('position')->orderBy('id');
    }

    /** Suku cadang / spare part untuk penggantian kerusakan kendaraan customer. */
    public function spareParts(): HasMany
    {
        return $this->hasMany(ServiceBookingSparePart::class)->orderBy('id');
    }

    /** Total biaya seluruh spare part yang diganti. */
    public function sparePartsTotal(): int
    {
        return (int) $this->spareParts->sum('subtotal');
    }

    /** Total estimasi tagihan servis: jasa servis + total spare part. */
    public function grandTotal(): int
    {
        return (int) ($this->service_price ?? 0) + $this->sparePartsTotal();
    }

    /** Rating customer untuk mekanik booking ini (satu per booking). */
    public function mechanicRating(): HasOne
    {
        return $this->hasOne(MechanicRating::class);
    }

    /**
     * Customer pemilik booking boleh memberi rating satu kali setelah servis selesai dikerjakan mekanik.
     */
    public function canBeRatedBy(?User $user): bool
    {
        return $user !== null
            && $this->user_id === $user->id
            && $this->status === self::STATUS_COMPLETED
            && $this->mechanic_id !== null
            && ($this->relationLoaded('mechanicRating') ? $this->mechanicRating === null : ! $this->mechanicRating()->exists());
    }

    /** Batas temuan / rekomendasi mekanik. */
    public const MAX_FINDINGS_LENGTH = 2000;

    /** Mekanik ditugaskan / checklist diubah selama booking belum selesai atau batal. */
    public function canBeAssigned(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isAssignedTo(?User $user): bool
    {
        return $user !== null && $this->mechanic_id === $user->id;
    }

    /**
     * Salin daftar pekerjaan bawaan layanan bila booking belum punya checklist.
     */
    public function copyChecklistFromService(): void
    {
        if ($this->tasks()->exists()) {
            return;
        }

        foreach ($this->service->checklistItems() as $position => $name) {
            $this->tasks()->create(['name' => $name, 'position' => $position + 1]);
        }
    }

    /**
     * [jumlah pekerjaan yang sudah ditangani (selesai / dilewati), total pekerjaan].
     *
     * @return array{int, int}
     */
    public function taskProgress(): array
    {
        $this->loadMissing('tasks');

        return [$this->tasks->reject->isPending()->count(), $this->tasks->count()];
    }

    public function hasPendingTasks(): bool
    {
        $this->loadMissing('tasks');

        return $this->tasks->contains->isPending();
    }

    /**
     * Mekanik boleh mulai pada tanggal jadwal (sama dengan aturan admin).
     */
    public function canBeStarted(): bool
    {
        return $this->status === self::STATUS_CONFIRMED && ! $this->preferred_date->isAfter(today());
    }

    /**
     * Kapasitas bengkel: jumlah booking aktif maksimal per slot (tanggal + jam).
     */
    public static function slotCapacity(): int
    {
        return max(1, (int) config('dealer.service_slot_capacity', 3));
    }

    /** Format plat setelah dinormalisasi: 1-2 huruf, 1-4 angka, 1-3 huruf (contoh: "D 1234 ABC", "AB 2235 WEZ"). */
    public const PLATE_REGEX = '/^[A-Z]{1,2}\s[0-9]{1,4}\s[A-Z]{1,3}$/';

    /**
     * Plat nomor dinormalisasi: huruf besar, satu spasi antar bagian
     * ("b 1234  abc" / "b1234abc" / "d1234abc" → "B 1234 ABC").
     */
    public static function normalizePlate(string $plate): string
    {
        $plate = strtoupper(trim((string) preg_replace('/\s+/', ' ', $plate)));

        if (preg_match('/^([A-Z]{1,2})\s*([0-9]{1,4})\s*([A-Z]{0,3})$/', str_replace(' ', '', $plate), $parts)) {
            return trim("{$parts[1]} {$parts[2]} {$parts[3]}");
        }

        return $plate;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * @return array<int, string>
     */
    public function allowedTransitions(): array
    {
        return $this->withoutEarlyNoShow(self::TRANSITIONS[$this->status] ?? []);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function canBeCancelledByCustomer(): bool
    {
        return in_array($this->status, self::CUSTOMER_CANCELLABLE_STATUSES, true)
            && $this->canTransitionTo(self::STATUS_CANCELLED);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Catatan bawaan per status untuk form admin; status Tidak datang memakai noShowNote().
     *
     * @return array<string, string>
     */
    public function noteTemplates(): array
    {
        $templates = self::NOTE_TEMPLATES;
        $templates[self::STATUS_NO_SHOW] = $this->noShowNote();

        return $templates;
    }

    /**
     * Jam jadwal "HH:MM" (kolom time bisa berisi detik).
     */
    public function timeLabel(): string
    {
        return substr((string) $this->preferred_time, 0, 5);
    }

    /**
     * Pesan WhatsApp (wa.me) untuk admin/staff menghubungi customer booking servis secara manual.
     */
    public function whatsappContactUrl(?User $staff = null): ?string
    {
        $number = preg_replace('/^0/', '62', preg_replace('/\D/', '', (string) $this->phone));

        if ($number === '') {
            return null;
        }

        $this->loadMissing(['user', 'service']);
        $dealerName = (string) config('dealer.name', 'JAF Dealer');
        $sender = $staff?->name ? "saya {$staff->name} dari {$dealerName}" : "kami dari {$dealerName}";
        $scheduleText = $this->preferred_date->translatedFormat('l, d F Y')." pukul {$this->timeLabel()} WIB";
        $serviceName = $this->service?->name ?? 'servis';
        $vehicleLabel = trim("{$this->vehicle_model} ({$this->plate_number})");
        $text = "Halo {$this->user->name}, {$sender}. Menghubungi Anda terkait booking servis {$serviceName} untuk kendaraan {$vehicleLabel} pada {$scheduleText}. Apakah ada detail atau keluhan tambahan pada kendaraan Anda yang perlu kami persiapkan? Terima kasih.";

        return "https://wa.me/{$number}?text=".rawurlencode($text);
    }
}
