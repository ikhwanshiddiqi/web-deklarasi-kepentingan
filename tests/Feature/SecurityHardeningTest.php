<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Feature test pengerasan keamanan lintas fitur (Task 9.1).
 *
 * Mencakup:
 * - Perlindungan CSRF: form yang mengubah state menampilkan token `_token`
 *   tersembunyi sehingga web middleware group dapat memvalidasinya (Req 9.2).
 * - Konfigurasi cookie sesi aman: `http_only=true`, `same_site=lax`, dan
 *   `secure` DIKENDALIKAN env (bukan hard-coded) agar dev HTTP tetap jalan
 *   sementara produksi HTTPS dapat memaksakan cookie aman (Req 9.2).
 * - Rate limiter bernama `login` dan `otp` terdaftar (Req 9.2).
 *
 * Catatan: throttle login setelah 5 percobaan sudah diuji di LoginTest, jadi
 * tidak diduplikasi di sini.
 *
 * Requirements: 9.1, 9.2, 9.5
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_memuat_token_csrf(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        // Token CSRF tersembunyi hadir pada form login (Req 9.2).
        $response->assertSee('name="_token"', false);
    }

    public function test_halaman_register_memuat_token_csrf(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        // Form registrasi (langkah detail akun) menyertakan token CSRF (Req 9.2).
        $response->assertSee('name="_token"', false);
    }

    public function test_cookie_sesi_http_only(): void
    {
        // JavaScript tidak boleh dapat membaca cookie sesi (Req 9.2).
        $this->assertTrue(config('session.http_only'));
    }

    public function test_cookie_sesi_same_site_lax(): void
    {
        // same_site=lax memitigasi CSRF lintas situs (Req 9.2).
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_cookie_secure_dikendalikan_env_bukan_hardcoded(): void
    {
        // `secure` membaca SESSION_SECURE_COOKIE dari env. Default null/false
        // menjaga dev HTTP tetap berfungsi; produksi HTTPS menyetelnya true.
        // Yang penting: nilai TIDAK dipaksa true secara hard-coded (Req 9.2).
        $this->assertNotTrue(config('session.secure'));
    }

    public function test_rate_limiter_login_dan_otp_terdaftar(): void
    {
        // Limiter bernama terdaftar di AppServiceProvider (Req 9.2).
        $this->assertNotNull(RateLimiter::limiter('login'));
        $this->assertNotNull(RateLimiter::limiter('otp'));
    }
}
