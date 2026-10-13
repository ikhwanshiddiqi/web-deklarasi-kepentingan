<?php

namespace Tests\Feature;

use App\Exceptions\OtpCooldownException;
use App\Mail\OtpMail;
use App\Models\RegistrationOtp;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Pengujian OtpService.
 *
 * DB pengujian: PostgreSQL `portal_benturan_kepentingan_test` (lihat phpunit.xml),
 * RefreshDatabase menjalankan migration segar. Mail::fake() mencegah pengiriman nyata.
 *
 * Requirements: 1.2, 1.3, 1.4, 1.5, 1.8
 */
class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OtpService();
        Mail::fake();
    }

    /**
     * Ambil kode plaintext dari OtpMail yang "dikirim" ke sebuah email.
     */
    private function capturedCodeFor(string $email): string
    {
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

    public function test_send_stores_only_hash_and_queues_mail(): void
    {
        $email = 'budi@sig.id';

        $result = $this->service->send($email, '203.0.113.10');

        $this->assertSame(['sent' => true], $result);

        $otp = RegistrationOtp::where('email', $email)->firstOrFail();
        $code = $this->capturedCodeFor($email);

        // Kode plaintext 6 digit.
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // Yang disimpan HANYA hash, bukan plaintext (Req 1.2, 1.8).
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertSame(hash('sha256', $code), $otp->code_hash);
        $this->assertSame(0, $otp->attempt_count);
        $this->assertNull($otp->consumed_at);
        $this->assertNotNull($otp->expires_at);
        $this->assertNotNull($otp->last_sent_at);
    }

    public function test_send_normalizes_email(): void
    {
        $this->service->send('  Budi@SIG.ID  ');

        $this->assertDatabaseHas('registration_otps', ['email' => 'budi@sig.id']);
        $this->assertDatabaseMissing('registration_otps', ['email' => '  Budi@SIG.ID  ']);
    }

    public function test_verify_succeeds_with_correct_code_and_marks_consumed(): void
    {
        $email = 'siti@sig.id';
        $this->service->send($email);
        $code = $this->capturedCodeFor($email);

        $this->assertTrue($this->service->verify($email, $code));

        $otp = RegistrationOtp::where('email', $email)->firstOrFail();
        $this->assertNotNull($otp->consumed_at);

        // Sekali pakai: verifikasi kedua gagal.
        $this->assertFalse($this->service->verify($email, $code));
    }

    public function test_verify_fails_with_wrong_code_and_increments_attempts(): void
    {
        $email = 'anwar@sig.id';
        $this->service->send($email);
        $code = $this->capturedCodeFor($email);

        $wrong = $code === '000000' ? '111111' : '000000';

        $this->assertFalse($this->service->verify($email, $wrong));

        $otp = RegistrationOtp::where('email', $email)->firstOrFail();
        $this->assertSame(1, $otp->attempt_count);
        $this->assertNull($otp->consumed_at);
    }

    public function test_verify_fails_when_expired(): void
    {
        $email = 'rina@sig.id';
        $this->service->send($email);
        $code = $this->capturedCodeFor($email);

        // Majukan waktu melewati masa berlaku 5 menit.
        $this->travel(OtpService::EXPIRY_MINUTES + 1)->minutes();

        $this->assertFalse($this->service->verify($email, $code));

        $this->travelBack();
    }

    public function test_verify_fails_after_max_attempts(): void
    {
        $email = 'dedi@sig.id';
        $this->service->send($email);
        $code = $this->capturedCodeFor($email);
        $wrong = $code === '000000' ? '111111' : '000000';

        // Habiskan batas percobaan dengan kode salah.
        for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
            $this->assertFalse($this->service->verify($email, $wrong));
        }

        $otp = RegistrationOtp::where('email', $email)->firstOrFail();
        $this->assertSame(OtpService::MAX_ATTEMPTS, $otp->attempt_count);

        // Bahkan kode BENAR ditolak setelah melebihi batas percobaan (Req 1.5).
        $this->assertFalse($this->service->verify($email, $code));
        $this->assertNull($otp->fresh()->consumed_at);
    }

    public function test_resend_invalidates_previous_code(): void
    {
        $email = 'eka@sig.id';

        // Kirim pertama.
        $this->service->send($email);
        $firstCode = $this->capturedCodeFor($email);

        // Lewati jeda cooldown lalu kirim ulang.
        $this->travel(OtpService::RESEND_COOLDOWN_SECONDS + 1)->seconds();
        $this->service->send($email);

        // Hanya satu baris aktif (belum consumed) yang tersisa.
        $activeCount = RegistrationOtp::where('email', $email)->whereNull('consumed_at')->count();
        $this->assertSame(1, $activeCount);

        // Kode lama tidak lagi valid (Req 1.3).
        $this->assertFalse($this->service->verify($email, $firstCode));

        $this->travelBack();
    }

    public function test_cooldown_blocks_immediate_resend(): void
    {
        $email = 'fajar@sig.id';
        $this->service->send($email);

        $this->expectException(OtpCooldownException::class);
        $this->service->send($email);
    }

    public function test_max_sends_per_hour_blocks_further_sends(): void
    {
        $email = 'gita@sig.id';

        // Kirim hingga batas, lewati cooldown tiap kali.
        for ($i = 0; $i < OtpService::MAX_SENDS_PER_HOUR; $i++) {
            $this->service->send($email);
            $this->travel(OtpService::RESEND_COOLDOWN_SECONDS + 1)->seconds();
        }

        $this->expectException(OtpCooldownException::class);
        $this->service->send($email);

        $this->travelBack();
    }

    public function test_is_verified_true_only_within_window_after_consumption(): void
    {
        $email = 'hadi@sig.id';
        $this->service->send($email);
        $code = $this->capturedCodeFor($email);

        $this->assertFalse($this->service->isVerified($email));

        $this->service->verify($email, $code);
        $this->assertTrue($this->service->isVerified($email));

        // Setelah jendela verifikasi lewat, tidak lagi dianggap terverifikasi.
        $this->travel(OtpService::VERIFIED_WINDOW_MINUTES + 1)->minutes();
        $this->assertFalse($this->service->isVerified($email));
        $this->travelBack();
    }

    public function test_ip_rate_limit_blocks_after_threshold(): void
    {
        $ip = '198.51.100.7';
        RateLimiter::clear('otp-send-ip:'.$ip);

        // Isi RateLimiter per IP hingga ambang batas.
        for ($i = 0; $i < OtpService::IP_MAX_ATTEMPTS; $i++) {
            RateLimiter::hit('otp-send-ip:'.$ip, OtpService::IP_DECAY_SECONDS);
        }

        $this->expectException(OtpCooldownException::class);
        $this->service->send('indah@sig.id', $ip);
    }
}
