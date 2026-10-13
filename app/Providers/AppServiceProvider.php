<?php

namespace App\Providers;

use App\Models\ConflictDeclaration;
use App\Policies\DeclarationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel 11 auto-discovers App\Policies\DeclarationPolicy untuk
        // App\Models\ConflictDeclaration berdasarkan konvensi penamaan. Kita
        // daftarkan eksplisit agar otorisasi deklarasi (Req 6.1) tidak bergantung
        // semata pada konvensi dan tetap aktif bila struktur folder berubah.
        Gate::policy(ConflictDeclaration::class, DeclarationPolicy::class);

        $this->configureRateLimiters();
    }

    /**
     * Daftarkan rate limiter bernama untuk endpoint sensitif (Req 9.2).
     *
     * Catatan keamanan:
     * - `login` dan `otp` membatasi penyalahgunaan form login & registrasi OTP
     *   pada lapis jaringan berdasarkan IP (10 permintaan/menit). Ini melengkapi
     *   throttle per-identitas di LoginRequest dan cap per-email di OtpService,
     *   sehingga pembatasan bekerja berlapis.
     * - Limiter didaftarkan eksplisit (bukan hanya `throttle:10,1`) agar kebijakan
     *   terpusat, mudah diaudit, dan konsisten di seluruh route sensitif.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
