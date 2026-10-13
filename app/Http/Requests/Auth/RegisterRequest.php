<?php

namespace App\Http\Requests\Auth;

use App\Services\OtpService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validasi data registrasi akun final.
 *
 * - Email: wajib, domain `@sig.id`, unik di `users.email` (setelah dinormalkan).
 * - NIK: wajib, tepat 16 digit angka, unik di `users.nik`.
 * - Password: wajib, dikonfirmasi, minimal 8 karakter mengandung huruf + angka.
 * - Email WAJIB sudah terverifikasi OTP (Req 1.7) — dicek via OtpService::isVerified.
 *
 * Requirements: 1.1, 1.6, 1.7
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalkan email sebelum validasi (penting untuk aturan unique).
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
            'email' => [
                'required', 'string', 'email', 'max:255',
                'regex:/@sig\.id$/i',
                Rule::unique('users', 'email'),
            ],
            'nik' => [
                'required', 'string',
                'regex:/^[0-9]{16}$/',
                Rule::unique('users', 'nik'),
            ],
            'password' => [
                'required', 'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    /**
     * Pastikan email telah diverifikasi OTP sebelum akun dibuat (Req 1.7).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $email = (string) $this->input('email');

            if ($email === '') {
                return;
            }

            if (! app(OtpService::class)->isVerified($email)) {
                $validator->errors()->add(
                    'email',
                    'Email belum diverifikasi. Silakan ulangi proses verifikasi OTP.',
                );
            }
        });
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
            'email.unique' => 'Email ini tidak dapat digunakan.',
            'nik.required' => 'NIK wajib diisi.',
            'nik.regex' => 'NIK harus terdiri dari tepat 16 digit angka.',
            'nik.unique' => 'NIK ini tidak dapat digunakan.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
        ];
    }
}
