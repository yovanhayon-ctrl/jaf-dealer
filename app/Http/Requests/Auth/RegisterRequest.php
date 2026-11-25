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
        // Di luar environment testing, cek DNS domain email agar domain fiktif (mis. @contoh.com) langsung ditolak.
        $emailRule = app()->environment('testing') ? 'email' : 'email:rfc,dns';

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', $emailRule, 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:'.self::PHONE_REGEX],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
