<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OtpService;
use App\Mail\OtpMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pengujian pencatatan audit event registrasi (Requirement 7.1, 7.2, 7.3).
 *
 * Memastikan registrasi sukses menulis tepat satu baris audit dengan
 * event='registered', user_id terisi, target_type=User::class, target_id=user id,
 * dan TIDAK memuat password/OTP (Req 7.3).
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * Verifikasi OTP untuk email agar registrasi dapat dilanjutkan,
     * mengembalikan kode plaintext yang ditangkap dari OtpMail.
     */
    private function verifyOtpFor(string $email): string
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
        $this->assertTrue(app(OtpService::class)->verify($email, (string) $code));

        return (string) $code;
    }

    public function test_successful_registration_writes_single_registered_audit_row(): void
    {
        $email = 'audit@sig.id';
        $password = 'Rahasia123';
        $code = $this->verifyOtpFor($email);

        $response = $this->post('/register', [
            'email' => $email,
            'nik' => '1234567890123456',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertRedirect();

        $user = User::where('email', $email)->firstOrFail();

        // Tepat satu baris audit tercatat.
        $this->assertSame(1, AuditLog::count());

        $log = AuditLog::firstOrFail();
        $this->assertSame(AuditService::EVENT_REGISTERED, $log->event);
        $this->assertSame('registered', $log->event);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(User::class, $log->target_type);
        $this->assertSame($user->id, (int) $log->target_id);
        $this->assertNotNull($log->created_at);
    }

    public function test_audit_row_never_contains_password_or_otp(): void
    {
        $email = 'noleak@sig.id';
        $password = 'SangatRahasia987';
        $code = $this->verifyOtpFor($email);

        $this->post('/register', [
            'email' => $email,
            'nik' => '6543210987654321',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect();

        $log = AuditLog::firstOrFail();

        // Atribut baris audit hanya kolom yang diharapkan — tidak ada payload bebas.
        $this->assertEqualsCanonicalizing(
            ['id', 'user_id', 'event', 'target_type', 'target_id', 'created_at'],
            array_keys($log->getAttributes()),
        );

        // Nilai sensitif (password plaintext / kode OTP) tidak muncul di manapun.
        $serialized = json_encode($log->getAttributes());
        $this->assertStringNotContainsString($password, $serialized);
        $this->assertStringNotContainsString($code, $serialized);
    }

    public function test_audit_service_log_resolves_authenticated_user_when_user_id_omitted(): void
    {
        $user = User::create([
            'email' => 'actor@sig.id',
            'nik' => '1010101010101010',
            'password' => 'Rahasia123',
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $log = app(AuditService::class)->log(AuditService::EVENT_PROFILE_UPDATED, $user);

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('profile_updated', $log->event);
        $this->assertSame(User::class, $log->target_type);
        $this->assertSame($user->id, (int) $log->target_id);
    }

    public function test_audit_service_log_without_target_stores_null_target_columns(): void
    {
        $log = app(AuditService::class)->log('some_event');

        $this->assertNull($log->user_id);
        $this->assertNull($log->target_type);
        $this->assertNull($log->target_id);
        $this->assertSame('some_event', $log->event);
    }
}
