<?php

namespace App\Services;

use App\Exceptions\OtpCooldownException;
use App\Mail\OtpMail;
use App\Models\RegistrationOtp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Layanan OTP registrasi email.
 *
 * Tanggung jawab:
 * - Membuat OTP numerik 6 digit secara kriptografis dan menyimpan HANYA hash-nya.
 * - Menegakkan masa berlaku 5 menit, cap 5 percobaan salah, dan sekali pakai.
 * - Menegakkan jeda resend 60 detik, maksimal 5 pengiriman/email/jam, dan rate limit per IP.
 * - Membatalkan kode lama setiap kali kode baru dibuat (hanya kode terbaru yang valid).
 * - TIDAK PERNAH menulis kode plaintext ke log atau menyimpannya sebagai teks biasa.
 *
 * Requirements: 1.2, 1.3, 1.4, 1.5, 1.8
 */
class OtpService
{
    /** Panjang OTP (digit). */
    public const OTP_LENGTH = 6;

    /** Masa berlaku OTP dalam menit. */
    public const EXPIRY_MINUTES = 5;

    /** Maksimal percobaan verifikasi salah sebelum OTP dinonaktifkan. */
    public const MAX_ATTEMPTS = 5;

    /** Jeda minimal (detik) antar pengiriman OTP untuk email yang sama. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /** Maksimal pengiriman OTP per email per jam. */
    public const MAX_SENDS_PER_HOUR = 5;

    /**
     * Jendela (menit) sejak OTP dikonsumsi, selama itu email dianggap "baru diverifikasi".
     * Dipakai alur registrasi untuk memastikan OTP sudah diverifikasi sebelum membuat akun.
     */
    public const VERIFIED_WINDOW_MINUTES = 15;

    /** Batas rate limit per IP (jumlah pengiriman). */
    public const IP_MAX_ATTEMPTS = 10;

    /** Jendela rate limit per IP (detik). */
    public const IP_DECAY_SECONDS = 3600;

    /**
     * Kirim OTP ke email.
     *
     * @return array{sent: bool}
     *
     * @throws OtpCooldownException Bila melanggar jeda resend atau batas pengiriman.
     */
    public function send(string $email, ?string $ip = null): array
    {
        $email = $this->normalizeEmail($email);
        $now = Carbon::now();

        // (1.4) Rate limit per IP via RateLimiter Laravel.
        if ($ip !== null && $ip !== '') {
            $ipKey = 'otp-send-ip:'.$ip;

            if (RateLimiter::tooManyAttempts($ipKey, self::IP_MAX_ATTEMPTS)) {
                throw new OtpCooldownException(
                    'Terlalu banyak permintaan OTP dari jaringan ini. Coba lagi nanti.',
                    RateLimiter::availableIn($ipKey),
                );
            }
        }

        // (1.3) Jeda minimal 60 detik: cek kode aktif terbaru untuk email ini.
        $latest = $this->latestActiveOtp($email);

        if ($latest !== null && $latest->last_sent_at !== null) {
            $elapsed = $latest->last_sent_at->diffInSeconds($now);

            if ($elapsed < self::RESEND_COOLDOWN_SECONDS) {
                throw new OtpCooldownException(
                    'Mohon tunggu sebelum meminta kode baru.',
                    self::RESEND_COOLDOWN_SECONDS - (int) $elapsed,
                );
            }
        }

        // (1.4) Maksimal 5 pengiriman per email per jam (hitung baris dibuat 1 jam terakhir).
        $sendsLastHour = RegistrationOtp::query()
            ->where('email', $email)
            ->where('created_at', '>=', $now->copy()->subHour())
            ->count();

        if ($sendsLastHour >= self::MAX_SENDS_PER_HOUR) {
            throw new OtpCooldownException(
                'Batas pengiriman kode untuk email ini telah tercapai. Coba lagi dalam satu jam.',
            );
        }

        // (1.3) Batalkan kode sebelumnya: tandai semua OTP aktif email ini sebagai consumed.
        RegistrationOtp::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => $now]);

        // (1.2) Generator acak kriptografis 6 digit, zero-padded.
        $code = $this->generateCode();

        // (1.2, 1.8) Simpan HANYA hash. sha256 untuk perbandingan constant-time sederhana.
        RegistrationOtp::create([
            'email' => $email,
            'code_hash' => $this->hashCode($code),
            'expires_at' => $now->copy()->addMinutes(self::EXPIRY_MINUTES),
            'attempt_count' => 0,
            'consumed_at' => null,
            'last_sent_at' => $now,
            'ip_address' => $ip,
        ]);

        // Catat percobaan IP (hanya setelah lolos semua pengecekan).
        if ($ip !== null && $ip !== '') {
            RateLimiter::hit('otp-send-ip:'.$ip, self::IP_DECAY_SECONDS);
        }

        // (1.8) Kode plaintext hanya ada di badan email, tidak pernah di-log.
        Mail::to($email)->send(new OtpMail($code));

        return ['sent' => true];
    }

    /**
     * Verifikasi OTP untuk email.
     *
     * Mengembalikan true hanya bila kode cocok, belum kedaluwarsa, belum dipakai,
     * dan belum melebihi batas percobaan. Pada kode salah, attempt_count dinaikkan.
     *
     * Requirements: 1.5
     */
    public function verify(string $email, string $code): bool
    {
        $email = $this->normalizeEmail($email);

        $otp = RegistrationOtp::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($otp === null) {
            return false;
        }

        // Kedaluwarsa atau sudah melebihi batas percobaan => tolak tanpa perubahan.
        if ($otp->isExpired() || $otp->attempt_count >= self::MAX_ATTEMPTS) {
            return false;
        }

        $matches = hash_equals($otp->code_hash, $this->hashCode($code));

        if (! $matches) {
            // (1.5) Naikkan hitungan percobaan salah.
            $otp->increment('attempt_count');

            return false;
        }

        // (1.5) Tandai sekali pakai.
        $otp->forceFill(['consumed_at' => Carbon::now()])->save();

        return true;
    }

    /**
     * Apakah email baru saja diverifikasi (ada OTP consumed dalam jendela terbatas).
     *
     * Dipakai alur registrasi untuk memastikan kepemilikan inbox terkonfirmasi
     * sebelum akun dibuat. Pilihan desain: andalkan baris OTP consumed di DB
     * (bukan session) agar stateless terhadap request terpisah.
     */
    public function isVerified(string $email): bool
    {
        $email = $this->normalizeEmail($email);

        return RegistrationOtp::query()
            ->where('email', $email)
            ->whereNotNull('consumed_at')
            ->where('consumed_at', '>=', Carbon::now()->subMinutes(self::VERIFIED_WINDOW_MINUTES))
            ->exists();
    }

    /**
     * Ambil OTP aktif terbaru (belum dipakai) untuk email.
     */
    protected function latestActiveOtp(string $email): ?RegistrationOtp
    {
        return RegistrationOtp::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Normalkan email: trim + lowercase (multibyte-safe).
     */
    protected function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Hitung hash OTP (sha256). Dipakai saat simpan dan saat perbandingan.
     */
    protected function hashCode(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * Hasilkan OTP numerik 6 digit secara kriptografis, zero-padded.
     */
    protected function generateCode(): string
    {
        $max = (10 ** self::OTP_LENGTH) - 1;

        return str_pad((string) random_int(0, $max), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }
}
