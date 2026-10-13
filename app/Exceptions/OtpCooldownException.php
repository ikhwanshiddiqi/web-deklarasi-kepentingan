<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar ketika permintaan kirim OTP melanggar jeda minimal 60 detik
 * atau melebihi batas pengiriman (per email / per IP). Controller memetakan
 * ini ke respons 429 / pesan validasi netral.
 *
 * Requirements: 1.3, 1.4
 */
class OtpCooldownException extends RuntimeException
{
    /**
     * Sisa detik sebelum pengiriman ulang diperbolehkan (jika relevan).
     */
    public ?int $retryAfterSeconds;

    public function __construct(string $message = 'Terlalu banyak permintaan OTP. Coba lagi nanti.', ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
        $this->retryAfterSeconds = $retryAfterSeconds;
    }
}
