<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi permintaan kirim OTP registrasi.
 *
 * - Email wajib, format valid, dan berakhiran `@sig.id` (case-insensitive).
 * - Email dinormalkan (trim + lowercase) sebelum divalidasi (Req 1.1).
 *
 * Requirements: 1.1
 */
class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalkan email sebelum validasi: trim + lowercase.
     */
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
            'email.max' => 'Email terlalu panjang.',
        ];
    }
}
