<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\OtpCooldownException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    private const NEUTRAL_SEND_MESSAGE =
    'Jika email memenuhi syarat, kode verifikasi telah dikirim. Silakan periksa kotak masuk Anda.';

    public function __construct(
        private readonly OtpService $otpService,
        private readonly AuditService $auditService,
    ) {}

    public function show(): View
    {
        return view('auth.register');
    }

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');

        try {
            if (! $this->emailBelongsToVerifiedUser($email)) {
                $this->otpService->send($email, $request->ip());
            }
        } catch (OtpCooldownException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 429);
        }

        return response()->json([
            'message' => self::NEUTRAL_SEND_MESSAGE,
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');
        $code = (string) $request->validated('code');

        $verified = $this->otpService->verify($email, $code);

        return response()->json([
            'verified' => $verified,
            'message' => $verified
                ? 'Verifikasi berhasil.'
                : 'Kode verifikasi tidak valid atau telah kedaluwarsa.',
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $email = (string) $request->validated('email');

        if (! $this->otpService->isVerified($email)) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'email' => 'Email belum diverifikasi. Silakan ulangi proses verifikasi OTP.',
                ]);
        }

        $user = User::create([
            'email' => $email,
            'nik' => (string) $request->validated('nik'),
            'password' => (string) $request->validated('password'),
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $this->auditService->log(AuditService::EVENT_REGISTERED, $user, $user->id);

        return redirect()->to($this->postRegistrationRedirect());
    }

    private function emailBelongsToVerifiedUser(string $email): bool
    {
        return User::query()
            ->where('email', $email)
            ->whereNotNull('email_verified_at')
            ->exists();
    }

    private function postRegistrationRedirect(): string
    {
        foreach (['account.settings', 'account.edit', 'profile.edit'] as $name) {
            if (\Illuminate\Support\Facades\Route::has($name)) {
                return route($name);
            }
        }

        return url('/account/settings');
    }
}
