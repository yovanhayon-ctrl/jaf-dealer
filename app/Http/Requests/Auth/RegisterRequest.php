<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi nomor HP: buang spasi/tanda hubung/titik, ubah +62/62 menjadi 0.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => $this->normalizePhone($this->input('phone')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Di luar environment testing, cek DNS + MX Record agar domain tanpa mail server (mis. @contoh.com) ditolak.
        $emailRules = [
            'required',
            'string',
            app()->environment('testing') ? 'email' : 'email:rfc,dns',
            'max:255',
            'unique:users,email',
        ];

        if (! app()->environment('testing')) {
            $emailRules[] = function (string $attribute, mixed $value, \Closure $fail): void {
                $domain = substr(strrchr((string) $value, '@') ?: '', 1);

                if ($domain === '' || ! checkdnsrr($domain, 'MX')) {
                    $fail('Domain email tersebut tidak dapat menerima email. Gunakan alamat email yang aktif.');
                }
            };
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => $emailRules,
            'phone' => ['required', 'regex:'.self::PHONE_REGEX],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
