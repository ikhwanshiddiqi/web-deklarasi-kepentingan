<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email berisi kode OTP registrasi.
 *
 * Kode plaintext hanya hidup di dalam objek ini dan badan email;
 * tidak pernah ditulis ke log atau disimpan sebagai teks biasa (Req 1.8).
 */
class OtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $code  Kode OTP 6 digit (plaintext, hanya untuk badan email).
     */
    public function __construct(
        public string $code,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode Verifikasi (OTP) Portal Benturan Kepentingan',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'code' => $this->code,
                'expiryMinutes' => \App\Services\OtpService::EXPIRY_MINUTES,
            ],
        );
    }
}
