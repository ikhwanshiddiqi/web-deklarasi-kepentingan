<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Layanan pencatatan audit (Requirement 7).
 *
 * Mencatat event penting ke tabel `audit_logs`: registrasi, perubahan profil,
 * pengiriman deklarasi, pembuatan ulang PDF, unduhan dokumen, dan perubahan
 * status akun.
 *
 * Prinsip keamanan (Req 7.3): layanan ini TIDAK PERNAH menerima atau menyimpan
 * password, token sesi, atau OTP. Signature sengaja dibatasi pada nama event
 * (string) dan model sasaran opsional — tidak ada jalur untuk memasukkan data
 * sensitif ke dalam baris audit.
 *
 * Requirements: 7.1, 7.2, 7.3
 */
class AuditService
{
    /** Event: akun berhasil didaftarkan. */
    public const EVENT_REGISTERED = 'registered';

    /** Event: pengguna berhasil login. */
    public const EVENT_LOGGED_IN = 'logged_in';

    /** Event: profil karyawan diperbarui. */
    public const EVENT_PROFILE_UPDATED = 'profile_updated';

    /** Event: deklarasi benturan kepentingan dikirim. */
    public const EVENT_DECLARATION_SUBMITTED = 'declaration_submitted';

    /** Event: PDF deklarasi dibuat/dibuat ulang. */
    public const EVENT_PDF_GENERATED = 'pdf_generated';

    /** Event: PDF deklarasi diunduh. */
    public const EVENT_PDF_DOWNLOADED = 'pdf_downloaded';

    /** Event: status aktif/nonaktif akun diubah. */
    public const EVENT_ACCOUNT_STATUS_CHANGED = 'account_status_changed';

    /**
     * Catat sebuah event audit.
     *
     * @param  string      $event   Nama event (gunakan konstanta EVENT_* bila memungkinkan).
     * @param  Model|null  $target  Entitas sasaran event (opsional).
     * @param  int|null    $userId  Pelaku; bila null diambil dari user terautentikasi.
     *
     * Catatan keamanan (Req 7.3): metode ini hanya menerima nama event dan model
     * sasaran. Tidak ada parameter untuk payload bebas, sehingga password/token/OTP
     * tidak dapat masuk ke audit log.
     */
    public function log(string $event, ?Model $target = null, ?int $userId = null): AuditLog
    {
        // Resolusi pelaku: pakai argumen eksplisit, jika tidak ada ambil dari sesi auth.
        $resolvedUserId = $userId ?? Auth::id();

        return AuditLog::create([
            'user_id' => $resolvedUserId,
            'event' => $event,
            // target_type menyimpan nama kelas model (atau alias morph bila didaftarkan).
            'target_type' => $target !== null ? $target->getMorphClass() : null,
            'target_id' => $target !== null ? $target->getKey() : null,
        ]);
    }
}
