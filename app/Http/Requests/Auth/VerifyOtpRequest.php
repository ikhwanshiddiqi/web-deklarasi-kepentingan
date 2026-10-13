<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi permintaan verifikasi OTP.
 *
 * - Email dinormalkan (trim + lowercase) + harus berdomain `@sig.id`.
 * - Kode wajib, tepat 6 digit angka.
 *
 * Requirements: 1.1, 1.6
 */
class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => mb_strtolower(trim($this->input('email'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'regex:/@sig\.id$/i'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.regex' => 'Email harus menggunakan domain @sig.id.',
            'code.required' => 'Kode OTP wajib diisi.',
            'code.regex' => 'Kode OTP harus terdiri dari 6 digit angka.',
        ];
    }
}
