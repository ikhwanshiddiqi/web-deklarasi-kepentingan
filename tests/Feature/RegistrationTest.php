<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pengujian alur registrasi (RegisterController + Form Requests + rute).
 *
 * Requirements: 1.1, 1.6, 1.7, 1.9
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * Jalankan alur kirim + verifikasi OTP agar email dianggap terverifikasi,
     * mengembalikan kode plaintext yang ditangkap dari OtpMail.
     */
    private function sendAndCaptureCode(string $email): string
    {
        app(OtpService::class)->send($email);

        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use ($email, &$code) {
            if ($mail->hasTo($email)) {
                $code = $mail->code;

                return true;
            }

            return false;
        });

        $this->assertNotNull($code, "Tidak ada OtpMail yang dikirim ke {$email}");

        return (string) $code;
    }

    public function test_send_otp_rejects_non_sig_domain(): void
    {
        $response = $this->postJson('/register/send-otp', ['email' => 'budi@gmail.com']);

        $response->assertStatus(422)->assertJsonValidationErrorFor('email');
        $this->assertDatabaseCount('registration_otps', 0);
    }

    public function test_send_otp_returns_neutral_response(): void
    {
        $response = $this->postJson('/register/send-otp', ['email' => 'Budi@SIG.ID']);

        $response->assertOk()->assertJsonStructure(['message']);

        // Email dinormalkan sebelum penyimpanan OTP.
        $this->assertDatabaseHas('registration_otps', ['email' => 'budi@sig.id']);
    }

    public function test_send_otp_is_neutral_for_existing_user(): void
    {
        // Email milik akun terverifikasi: pengiriman di-skip diam-diam, respons tetap sama.
        User::create([
            'email' => 'ada@sig.id',
            'nik' => '1111111111111111',
            'nip' => '1980010100000001',
            'password' => 'Rahasia123',
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $existing = $this->postJson('/register/send-otp', ['email' => 'ada@sig.id']);
        $fresh = $this->postJson('/register/send-otp', ['email' => 'baru@sig.id']);

        $existing->assertOk();
        $fresh->assertOk();

        // Respons netral identik; tidak membocorkan keberadaan akun (Req 1.9).
        $this->assertSame($fresh->json('message'), $existing->json('message'));

        // Tidak ada OTP yang dibuat untuk email terdaftar.
        $this->assertDatabaseMissing('registration_otps', ['email' => 'ada@sig.id']);
    }

    public function test_verify_otp_with_correct_code_returns_verified(): void
    {
        $email = 'siti@sig.id';
        $code = $this->sendAndCaptureCode($email);

        $response = $this->postJson('/register/verify-otp', ['email' => $email, 'code' => $code]);

        $response->assertOk()->assertJson(['verified' => true]);
    }

    public function test_verify_otp_with_wrong_code_returns_not_verified(): void
    {
        $email = 'rudi@sig.id';
        $code = $this->sendAndCaptureCode($email);
        $wrong = $code === '000000' ? '111111' : '000000';

        $response = $this->postJson('/register/verify-otp', ['email' => $email, 'code' => $wrong]);

        $response->assertOk()->assertJson(['verified' => false]);
    }

    public function test_store_creates_active_user_when_otp_verified(): void
    {
        $email = 'dewi@sig.id';
        $code = $this->sendAndCaptureCode($email);
        $this->assertTrue(app(OtpService::class)->verify($email, $code));

        $response = $this->post('/register', [
            'email' => $email,
            'nik' => '1234567890123456',
            'nip' => '1980050500000005',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
        ]);

        $response->assertRedirect();

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame('1234567890123456', $user->nik);
        $this->assertSame('employee', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_store_creates_user_with_nip_when_submitted(): void
    {
        $email = 'nips@sig.id';
        $code = $this->sendAndCaptureCode($email);
        $this->assertTrue(app(OtpService::class)->verify($email, $code));

        $response = $this->post('/register', [
            'email' => $email,
            'nik' => '1234567890123456',
            'nip' => '198765432198765432',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
        ]);

        $response->assertRedirect();

        $user = User::where('email', $email)->firstOrFail();
        $this->assertSame('198765432198765432', $user->nip);
    }

    public function test_store_rejected_when_email_not_verified(): void
    {
        // Tidak ada verifikasi OTP untuk email ini.
        $response = $this->from('/register')->post('/register', [
            'email' => 'belum@sig.id',
            'nik' => '2222222222222222',
            'nip' => '1980060600000006',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
        ]);

        $response->assertRedirect('/register')->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'belum@sig.id']);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $email = 'dup@sig.id';
        User::create([
            'email' => $email,
            'nik' => '3333333333333333',
            'nip' => '1980030300000003',
            'password' => 'Rahasia123',
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $code = $this->sendAndCaptureCode($email);
        app(OtpService::class)->verify($email, $code);

        $response = $this->from('/register')->post('/register', [
            'email' => $email,
            'nik' => '4444444444444444',
            'nip' => '1980070700000007',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
        ]);

        $response->assertRedirect('/register')->assertSessionHasErrors('email');
    }

    public function test_store_rejects_duplicate_nik(): void
    {
        $nik = '5555555555555555';
        User::create([
            'email' => 'first@sig.id',
            'nik' => $nik,
            'nip' => '1980040400000004',
            'password' => 'Rahasia123',
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $email = 'second@sig.id';
        $code = $this->sendAndCaptureCode($email);
        app(OtpService::class)->verify($email, $code);

        $response = $this->from('/register')->post('/register', [
            'email' => $email,
            'nik' => $nik,
            'nip' => '1980080800000008',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
        ]);

        $response->assertRedirect('/register')->assertSessionHasErrors('nik');
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_store_rejects_nik_not_16_digits(): void
    {
        $email = 'nik@sig.id';
        $code = $this->sendAndCaptureCode($email);
        app(OtpService::class)->verify($email, $code);

        $response = $this->from('/register')->post('/register', [
            'email' => $email,
            'nik' => '12345',
            'nip' => '1980090900000009',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
        ]);

        $response->assertRedirect('/register')->assertSessionHasErrors('nik');
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_register_page_is_accessible(): void
    {
        $this->get('/register')->assertOk()->assertViewIs('auth.register');
    }
}
