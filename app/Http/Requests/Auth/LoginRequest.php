<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Permintaan login dengan email ATAU NIK (Requirement 2).
 *
 * Prinsip keamanan:
 * - Identitas dinormalkan; `@` menandakan email, selain itu NIK (Req 2.2).
 * - Autentikasi hanya terhadap akun terverifikasi (`email_verified_at`) & aktif
 *   (`is_active`) (Req 2.1). Kegagalan apa pun menghasilkan pesan generik tunggal
 *   pada field `identity` tanpa membocorkan bagian mana yang salah (Req 2.3).
 * - Rate limiting: maksimal 5 percobaan gagal lalu throttle berdasarkan
 *   identitas + IP (Req 2.4).
 *
 * Requirements: 2.1, 2.2, 2.3, 2.4
 */
class LoginRequest extends FormRequest
{
    /** Pesan generik tunggal untuk semua kegagalan login (Req 2.3). */
    private const GENERIC_ERROR = 'Email/NIK atau password salah.';

    /** Jumlah percobaan gagal sebelum throttle diterapkan (Req 2.4). */
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identity' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Normalisasi identitas: trim selalu; lowercase hanya bila tampak email.
     */
    protected function prepareForValidation(): void
    {
        $identity = trim((string) $this->input('identity', ''));

        if (Str::contains($identity, '@')) {
            $identity = Str::lower($identity);
        }

        $this->merge(['identity' => $identity]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identity.required' => 'Email atau NIK wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ];
    }

    /**
     * Lakukan autentikasi memakai pola Breeze: cek throttle → attempt →
     * verifikasi akun aktif & terverifikasi → bersihkan limiter.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identity = (string) $this->input('identity');
        $field = Str::contains($identity, '@') ? 'email' : 'nik';

        $credentials = [
            $field => $identity,
            'password' => (string) $this->input('password'),
        ];

        $remember = $this->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'identity' => self::GENERIC_ERROR,
            ]);
        }

        // Autentikasi dasar lolos. Pastikan akun terverifikasi & aktif (Req 2.1).
        // Jika tidak, logout kembali dan balas pesan generik yang sama (Req 2.3).
        $user = Auth::user();

        if (! $this->accountIsEligible($user)) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'identity' => self::GENERIC_ERROR,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Akun layak login hanya jika aktif DAN email sudah terverifikasi.
     */
    private function accountIsEligible(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->is_active === true
            && $user->email_verified_at !== null;
    }

    /**
     * Pastikan percobaan belum melewati batas (Req 2.4).
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identity' => sprintf(
                'Terlalu banyak percobaan login. Silakan coba lagi dalam %d detik.',
                $seconds,
            ),
        ]);
    }

    /**
     * Kunci rate limiter berbasis identitas + IP (Req 2.4).
     */
    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->input('identity')).'|'.$this->ip()
        );
    }
}
