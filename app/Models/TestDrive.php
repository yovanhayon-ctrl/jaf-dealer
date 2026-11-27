<?php

namespace App\Models;

use App\Models\Concerns\HasSchedule;
use Database\Factories\TestDriveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'car_id', 'sales_id', 'preferred_date', 'preferred_time', 'phone', 'notes',
    'status', 'admin_note',
])]
class TestDrive extends Model
{
    /** @use HasFactory<TestDriveFactory> */
    use HasFactory;

    use HasSchedule;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Customer tidak hadir pada jadwal yang sudah dikonfirmasi (lihat HasSchedule). */
    public const STATUS_NO_SHOW = 'no_show';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_NO_SHOW];

    /** Label bahasa Indonesia (sama dengan x-status-badge). */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu',
        self::STATUS_CONFIRMED => 'Dikonfirmasi',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_CANCELLED => 'Dibatalkan',
        self::STATUS_NO_SHOW => 'Tidak datang',
    ];

    /** Transisi status yang diizinkan (RANCANGAN §5). Status lain adalah status akhir. Tidak datang: lihat allowedTransitions(). */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_COMPLETED, self::STATUS_NO_SHOW, self::STATUS_CANCELLED],
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
        self::STATUS_CONFIRMED => 'Jadwal test drive Anda telah dikonfirmasi. Silakan datang ke dealer tepat waktu dan siapkan SIM A yang masih berlaku.',
        self::STATUS_COMPLETED => 'Test drive telah selesai dilaksanakan. Terima kasih atas kunjungan Anda ke dealer kami.',
        self::STATUS_CANCELLED => 'Mohon maaf, jadwal test drive dibatalkan karena '.self::NOTE_PLACEHOLDER,
    ];

    /** Status yang masih "memegang" jadwal: dipakai cek booking ganda & bentrok slot. */
    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED];

    /** Customer hanya boleh membatalkan saat status ini (RANCANGAN §5). */
    public const CUSTOMER_CANCELLABLE_STATUSES = [self::STATUS_PENDING];

    /** Slot jam booking (WIB), per jam 08:00–16:00. */
    public const TIME_SLOTS = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];

    /** Rentang tanggal booking: besok s/d hari ini + 30 hari (WIB). */
    public const MIN_DAYS_AHEAD = 1;

    public const MAX_DAYS_AHEAD = 30;

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'reminded_at' => 'datetime',
        ];
    }

    /**
     * Test drive yang masih memegang jadwal (pending / confirmed).
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

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function isAssignedTo(?User $user): bool
    {
        return $user !== null && $this->sales_id === $user->id;
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
     * Pesan WhatsApp (wa.me) untuk admin/sales menghubungi customer test drive secara manual.
     */
    public function whatsappContactUrl(?User $staff = null): ?string
    {
        $number = preg_replace('/^0/', '62', preg_replace('/\D/', '', (string) $this->phone));

        if ($number === '') {
            return null;
        }

        $this->loadMissing(['user', 'car.brand']);
        $dealerName = (string) config('dealer.name', 'JAF Dealer');
        $sender = $staff?->name ? "saya {$staff->name} dari {$dealerName}" : "kami dari {$dealerName}";
        $carLabel = trim("{$this->car->brand->name} {$this->car->name}");
        $scheduleText = $this->preferred_date->translatedFormat('l, d F Y')." pukul {$this->timeLabel()} WIB";
        $text = "Halo {$this->user->name}, {$sender}. Menghubungi Anda terkait jadwal test drive mobil {$carLabel} pada {$scheduleText}. Apakah jadwal tersebut sudah sesuai dan ada hal yang ingin ditanyakan sebelum test drive? Terima kasih.";

        return "https://wa.me/{$number}?text=".rawurlencode($text);
    }
}
