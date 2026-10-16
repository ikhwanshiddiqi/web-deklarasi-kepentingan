<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const GENERIC_ERROR = 'Email/NIP atau password salah.';

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

    protected function prepareForValidation(): void
    {
        $identity = trim((string) $this->input('identity', ''));

        if (Str::contains($identity, '@')) {
            $identity = Str::lower($identity);
        }

        $this->merge(['identity' => $identity]);
    }

    public function messages(): array
    {
        return [
            'identity.required' => 'Email atau NIP wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identity = (string) $this->input('identity');
        $field = Str::contains($identity, '@') ? 'email' : 'nip';

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

    private function accountIsEligible(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->is_active === true
            && $user->email_verified_at !== null;
    }

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

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->input('identity')).'|'.$this->ip()
        );
    }
}
