<?php

namespace App\Http\Requests;

use App\Actions\BookService;
use App\Http\Requests\Concerns\NormalizesDigits;
use App\Http\Requests\Concerns\NormalizesPhone;
use App\Models\Car;
use App\Models\Service;
use App\Models\ServiceBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServiceBookingRequest extends FormRequest
{
    use NormalizesDigits, NormalizesPhone;

    public const ADMIN_MESSAGE = 'Akun staf dealer tidak dapat booking servis. Gunakan akun customer.';

    public const MAX_COMPLAINT_LENGTH = 500;

    /** Sama dengan batas tahun katalog: pemilik mobil klasik juga bisa booking servis. */
    public const MIN_VEHICLE_YEAR = Car::MIN_YEAR;

    public const MAX_MILEAGE = 2_000_000;

    /**
     * Hanya customer; admin diarahkan kembali dengan pesan (bukan halaman 403).
     */
    public function authorize(): bool
    {
        return ! $this->user()->isStaff();
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()->route('service-bookings.create')->with('error', self::ADMIN_MESSAGE)
        );
    }

    /**
     * Plat dirapikan ("b1234abc" → "B 1234 ABC"); km boleh memakai titik ribuan.
     */
    protected function prepareForValidation(): void
    {
        $complaint = $this->input('complaint');

        $this->merge([
            'vehicle_model' => Str::squish((string) $this->input('vehicle_model')),
            'plate_number' => ServiceBooking::normalizePlate((string) $this->input('plate_number')),
            'vehicle_year' => $this->digitsOnly('vehicle_year'),
            'mileage' => $this->digitsOnly('mileage'),
            'phone' => $this->normalizePhone($this->input('phone')),
            'complaint' => is_string($complaint) ? trim($complaint) : $complaint,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'vehicle_model' => ['required', 'string', 'max:100'],
            'plate_number' => ['required', 'string', 'max:15', 'regex:'.ServiceBooking::PLATE_REGEX],
            'vehicle_year' => ['nullable', 'integer', 'min:'.self::MIN_VEHICLE_YEAR, 'max:'.(today()->year + 1)],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MILEAGE],
            // Tanggal WIB (timezone aplikasi Asia/Jakarta): hari ini (bila slot masih tersedia) atau besok s/d 30 hari ke depan.
            'preferred_date' => [
                'required', 'date_format:Y-m-d',
                'after_or_equal:'.self::firstDate(),
                'before_or_equal:'.self::lastDate(),
            ],
            'preferred_time' => ['required', 'string', Rule::in(ServiceBooking::TIME_SLOTS)],
            'phone' => ['required', 'regex:'.self::PHONE_REGEX],
            'complaint' => ['nullable', 'string', 'max:'.self::MAX_COMPLAINT_LENGTH],
            // Opsional: kosong = dipilihkan bengkel. Ketersediaan & jadwal mekanik dicek di BookService::conflicts.
            'mechanic_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Aturan layanan, booking ganda per plat, dan kapasitas slot (dicek ulang di BookService dengan lock).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['preferred_date', 'preferred_time'])) {
                    return;
                }

                $date = $this->string('preferred_date')->value();
                $time = $this->string('preferred_time')->value();

                if (Carbon::parse($date, config('app.timezone', 'Asia/Jakarta'))->isSunday()) {
                    $validator->errors()->add('preferred_date', 'Dealer tutup pada hari Minggu. Silakan pilih hari Senin–Sabtu.');

                    return;
                }

                $validSlots = ServiceBooking::availableTimeSlots($date);

                if (! in_array($time, $validSlots, true)) {
                    $validator->errors()->add(
                        'preferred_time',
                        'Jam servis yang dipilih sudah lewat atau terlalu mepet (minimal jeda 1 jam dari sekarang). Silakan pilih jam lain.'
                    );

                    return;
                }

                if ($validator->errors()->hasAny(['service_id', 'plate_number', 'mechanic_id'])) {
                    return;
                }

                $conflicts = app(BookService::class)->conflicts(
                    $this->user(),
                    Service::findOrFail($this->integer('service_id')),
                    $this->string('plate_number')->value(),
                    $date,
                    $time,
                    $this->filled('mechanic_id') ? $this->integer('mechanic_id') : null,
                );

                foreach ($conflicts as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    public static function firstDate(): string
    {
        return ServiceBooking::firstAvailableDate();
    }

    public static function lastDate(): string
    {
        return today()->addDays(ServiceBooking::MAX_DAYS_AHEAD)->toDateString();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'service_id' => 'layanan',
            'vehicle_model' => 'merek & model kendaraan',
            'plate_number' => 'plat nomor',
            'vehicle_year' => 'tahun kendaraan',
            'mileage' => 'kilometer',
            'preferred_date' => 'tanggal',
            'preferred_time' => 'jam',
            'phone' => 'nomor WhatsApp',
            'complaint' => 'keluhan',
            'mechanic_id' => 'mekanik',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $firstDate = self::firstDate();
        $first = Carbon::parse($firstDate)->translatedFormat('d M Y');
        $last = today()->addDays(ServiceBooking::MAX_DAYS_AHEAD)->translatedFormat('d M Y');
        $slots = ServiceBooking::TIME_SLOTS;

        $dateMinMessage = $firstDate === today()->toDateString()
            ? "Tanggal servis tidak boleh tanggal lampau. Silakan pilih jadwal mulai hari ini ({$first})."
            : "Pendaftaran servis untuk hari ini telah ditutup. Silakan pilih jadwal mulai besok ({$first}).";

        return [
            'service_id.required' => 'Pilih layanan servis.',
            'service_id.exists' => 'Layanan tidak ditemukan.',
            'plate_number.regex' => 'Format plat nomor tidak valid. Gunakan standar Indonesia: 1–2 huruf, 1–4 angka, dan 1–3 huruf (contoh: D 1234 ABC atau AB 2235 WEZ).',
            'preferred_date.after_or_equal' => $dateMinMessage,
            'preferred_date.before_or_equal' => "Tanggal servis paling lambat {$last}.",
            'preferred_time.in' => 'Pilih jam antara '.reset($slots).' dan '.end($slots).' WIB.',
            'phone.regex' => 'Nomor WhatsApp tidak valid. Gunakan format 08xx (10-13 digit).',
        ];
    }

    /**
     * Data tervalidasi dengan angka sebagai integer.
     *
     * @return array<string, mixed>
     */
    public function bookingData(): array
    {
        $data = $this->validated();

        return [
            ...$data,
            'vehicle_year' => isset($data['vehicle_year']) ? (int) $data['vehicle_year'] : null,
            'mileage' => isset($data['mileage']) ? (int) $data['mileage'] : null,
            'complaint' => ($data['complaint'] ?? null) ?: null,
            'mechanic_id' => isset($data['mechanic_id']) ? (int) $data['mechanic_id'] : null,
        ];
    }
}
