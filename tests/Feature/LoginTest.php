<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Feature test autentikasi login/logout (Requirement 2).
 *
 * Mencakup: login via email & NIK, pesan generik untuk kredensial salah,
 * blokir akun nonaktif/belum terverifikasi, dan throttle setelah 5 percobaan.
 *
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'rahasia-kuat-123';

    private const WRONG_PASSWORD = 'password-salah';

    private const GENERIC_ERROR = 'Email/NIK atau password salah.';

    protected function setUp(): void
    {
        parent::setUp();

        // Pastikan limiter bersih di setiap tes agar isolasi throttle terjaga.
        RateLimiter::clear('');
    }

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'budi@sig.id',
            'nik' => '1234567890123456',
            'password' => Hash::make(self::PASSWORD),
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
    }

    public function test_tamu_yang_mengakses_root_diarahkan_ke_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_halaman_login_dapat_dibuka(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_halaman_login_menampilkan_judul_dan_label_identitas(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        // Judul portal sesuai design.md 5.1 (Req 2.1).
        $response->assertSee('Portal Pernyataan Benturan Kepentingan');
        // Label field identitas tunggal email/NIK (Req 2.1, 2.2).
        $response->assertSee('Email perusahaan atau NIK');
    }

    public function test_login_via_email_berhasil_dan_redirect(): void
    {
        $user = $this->makeUser();

        $response = $this->post('/login', [
            'identity' => 'budi@sig.id',
            'password' => self::PASSWORD,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect();

        // Profil belum lengkap → diarahkan ke Setting Akun (Req 2.5).
        $response->assertRedirect('/account/settings');

        // Audit login tercatat (Req 7.1).
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => AuditService::EVENT_LOGGED_IN,
        ]);
    }

    public function test_admin_login_diarahkan_ke_beranda_admin(): void
    {
        $admin = $this->makeUser([
            'role' => 'admin',
            'email' => 'admin@sig.id',
            'nik' => '1111111111111111',
        ]);

        $response = $this->post('/login', [
            'identity' => 'admin@sig.id',
            'password' => self::PASSWORD,
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.home'));
    }

    public function test_chart_pengisian_hanya_muncul_di_beranda_admin(): void
    {
        $admin = $this->makeUser([
            'role' => 'admin',
            'email' => 'admin@sig.id',
            'nik' => '1111111111111111',
        ]);

        $employee = $this->makeUser([
            'role' => 'employee',
            'email' => 'employee@sig.id',
            'nik' => '2222222222222222',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.home'))
            ->assertSee('Progres pengisian deklarasi');

        $this->actingAs($employee)
            ->get(route('home'))
            ->assertDontSee('Progres pengisian deklarasi');
    }

    public function test_login_email_dinormalkan_huruf_besar_dan_spasi(): void
    {
        $user = $this->makeUser();

        $response = $this->post('/login', [
            'identity' => '  BUDI@SIG.ID  ',
            'password' => self::PASSWORD,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect();
    }

    public function test_login_via_nik_berhasil(): void
    {
        $user = $this->makeUser();

        $response = $this->post('/login', [
            'identity' => '1234567890123456',
            'password' => self::PASSWORD,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect();
    }

    public function test_password_salah_memberi_pesan_generik_dan_tidak_autentikasi(): void
    {
        $this->makeUser();

        $response = $this->from('/login')->post('/login', [
            'identity' => 'budi@sig.id',
            'password' => self::WRONG_PASSWORD,
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['identity' => self::GENERIC_ERROR]);

        // Pesan generik TIDAK boleh menyinggung field tertentu (Req 2.3).
        $response->assertSessionDoesntHaveErrors(['password']);
    }

    public function test_akun_nonaktif_tidak_dapat_login(): void
    {
        $this->makeUser(['is_active' => false]);

        $response = $this->from('/login')->post('/login', [
            'identity' => 'budi@sig.id',
            'password' => self::PASSWORD,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['identity' => self::GENERIC_ERROR]);
    }

    public function test_akun_belum_terverifikasi_tidak_dapat_login(): void
    {
        $this->makeUser(['email_verified_at' => null]);

        $response = $this->from('/login')->post('/login', [
            'identity' => 'budi@sig.id',
            'password' => self::PASSWORD,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['identity' => self::GENERIC_ERROR]);
    }

    public function test_percobaan_berlebihan_memicu_throttle(): void
    {
        $this->makeUser();

        // 5 percobaan gagal mencapai batas; percobaan ke-6 harus terkunci.
        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'identity' => 'budi@sig.id',
                'password' => self::WRONG_PASSWORD,
            ]);
        }

        $response = $this->from('/login')->post('/login', [
            'identity' => 'budi@sig.id',
            'password' => self::WRONG_PASSWORD,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('identity');

        $errors = session('errors')->get('identity');
        $this->assertStringContainsString('Terlalu banyak percobaan login', $errors[0]);
    }

    public function test_logout_mengakhiri_sesi(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
