<?php

namespace App\Services;

use App\Models\ConflictDeclaration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Layanan penyimpanan deklarasi benturan kepentingan (Requirement 4).
 *
 * Tanggung jawab utama (Task 6.3):
 * - Menyimpan deklarasi + kategori + rincian dalam SATU transaksi database
 *   sehingga tidak mungkin terjadi penulisan parsial (Req 4.11).
 * - Mengambil profil karyawan LANGSUNG dari database untuk di-snapshot
 *   (employee_name/nik/position/work_unit) agar PDF historis tidak berubah
 *   bila profil diedit kemudian (Req 4.12). Identitas tidak pernah diambil
 *   dari input browser (Req 6.3 desain).
 * - Menetapkan label `APPROVED` dan `submitted_at` pada zona `Asia/Jakarta`
 *   (Req 4.13).
 *
 * Catatan: pembuatan PDF (Task 7) TIDAK dilakukan di sini. `pdf_path` dibiarkan
 * null; controller yang akan memicu PdfService setelah penyimpanan berhasil.
 *
 * Requirements: 4.11, 4.12, 4.13
 */
class DeclarationService
{
    /** Label yang disimpan pada deklarasi setelah berhasil dikirim (Req 4.13). */
    public const APPROVED_LABEL = 'APPROVED';

    /**
     * Simpan deklarasi secara atomik beserta kategori & rincian.
     *
     * @param  array{
     *     declaration_status: string,
     *     categories: array<int, string>,
     *     details: array<int, array{category_key: string, related_party_name: string, situation_explanation: string}>
     * }  $data  Payload ter-normalisasi dari {@see \App\Http\Requests\StoreDeclarationRequest::normalized()}.
     *
     * @throws RuntimeException Bila invariant server dilanggar (belt-and-suspenders Req 4.9)
     *                          atau profil karyawan belum tersedia.
     */
    public function store(User $user, array $data): ConflictDeclaration
    {
        $status = (string) ($data['declaration_status'] ?? '');

        // Normalisasi defensif: untuk status `none`, kategori & rincian selalu kosong.
        $categories = $status === ConflictDeclaration::STATUS_HAS_CONFLICT
            ? $this->uniqueCategories($data['categories'] ?? [])
            : [];
        $details = $status === ConflictDeclaration::STATUS_HAS_CONFLICT
            ? array_values($data['details'] ?? [])
            : [];

        // Invariant server (belt-and-suspenders untuk Req 4.9): tiap rincian wajib
        // memakai kategori yang terpilih. FormRequest sudah menjamin ini, namun
        // kita cek ulang agar bug pemanggil tidak dapat melewati aturan.
        $this->assertDetailsReferenceSelectedCategories($status, $categories, $details);

        return DB::transaction(function () use ($user, $status, $categories, $details): ConflictDeclaration {
            // Ambil profil dari DB (bukan dari browser) untuk snapshot identitas (Req 4.12).
            $profile = $user->profile()->first();

            if ($profile === null) {
                // Seharusnya tidak terjadi karena route dijaga `profile.complete`,
                // tetapi kita gagalkan transaksi daripada menyimpan snapshot kosong.
                throw new RuntimeException(
                    'Profil karyawan tidak ditemukan; deklarasi tidak dapat disimpan.'
                );
            }

            $declaration = ConflictDeclaration::create([
                'user_id' => $user->id,
                // Snapshot identitas karyawan (Req 4.12).
                'employee_name' => $profile->full_name,
                'employee_nik' => $user->nik,
                'employee_position' => $profile->position,
                'employee_work_unit' => $profile->work_unit,
                // Status + penanda sistem (Req 4.13).
                'declaration_status' => $status,
                'approved_label' => self::APPROVED_LABEL,
                'submitted_at' => Carbon::now('Asia/Jakarta'),
                // PDF dibuat terpisah pada Task 7; biarkan null di sini.
                'pdf_path' => null,
            ]);

            // Status `has_conflict`: simpan kategori terpilih & rincian Bagian C.
            // Status `none`: tidak menyimpan kategori/rincian apa pun (Req 4.5).
            if ($status === ConflictDeclaration::STATUS_HAS_CONFLICT) {
                foreach ($categories as $categoryKey) {
                    $declaration->categories()->create([
                        'category_key' => $categoryKey,
                    ]);
                }

                foreach ($details as $detail) {
                    $declaration->details()->create([
                        'category_key' => (string) ($detail['category_key'] ?? ''),
                        'related_party_name' => (string) ($detail['related_party_name'] ?? ''),
                        'situation_explanation' => (string) ($detail['situation_explanation'] ?? ''),
                    ]);
                }
            }

            // Muat ulang relasi agar pemanggil memperoleh objek yang lengkap.
            return $declaration->load(['categories', 'details']);
        });
    }

    /**
     * Perbarui deklarasi milik user yang sudah ada.
     */
    public function update(ConflictDeclaration $declaration, array $data): ConflictDeclaration
    {
        $status = (string) ($data['declaration_status'] ?? '');

        $categories = $status === ConflictDeclaration::STATUS_HAS_CONFLICT
            ? $this->uniqueCategories($data['categories'] ?? [])
            : [];
        $details = $status === ConflictDeclaration::STATUS_HAS_CONFLICT
            ? array_values($data['details'] ?? [])
            : [];

        $this->assertDetailsReferenceSelectedCategories($status, $categories, $details);

        return DB::transaction(function () use ($declaration, $status, $categories, $details): ConflictDeclaration {
            $declaration->update([
                'declaration_status' => $status,
                'approved_label' => self::APPROVED_LABEL,
            ]);

            $declaration->categories()->delete();
            $declaration->details()->delete();

            if ($status === ConflictDeclaration::STATUS_HAS_CONFLICT) {
                foreach ($categories as $categoryKey) {
                    $declaration->categories()->create([
                        'category_key' => $categoryKey,
                    ]);
                }

                foreach ($details as $detail) {
                    $declaration->details()->create([
                        'category_key' => (string) ($detail['category_key'] ?? ''),
                        'related_party_name' => (string) ($detail['related_party_name'] ?? ''),
                        'situation_explanation' => (string) ($detail['situation_explanation'] ?? ''),
                    ]);
                }
            }

            return $declaration->fresh()->load(['categories', 'details']);
        });
    }

    /**
     * Normalkan daftar kategori menjadi string unik tanpa nilai kosong.
     *
     * @param  array<int, mixed>  $categories
     * @return array<int, string>
     */
    private function uniqueCategories(array $categories): array
    {
        $normalized = array_map(static fn($value): string => trim((string) $value), $categories);
        $normalized = array_filter($normalized, static fn(string $value): bool => $value !== '');

        return array_values(array_unique($normalized));
    }

    /**
     * Pastikan setiap rincian memakai kategori yang terpilih (Req 4.9).
     *
     * @param  array<int, string>  $categories
     * @param  array<int, array<string, mixed>>  $details
     *
     * @throws RuntimeException
     */
    private function assertDetailsReferenceSelectedCategories(
        string $status,
        array $categories,
        array $details,
    ): void {
        // Pada status `none` tidak ada rincian yang disimpan sehingga tidak ada
        // yang perlu divalidasi di sini (FormRequest menolak kombinasi terlarang).
        if ($status !== ConflictDeclaration::STATUS_HAS_CONFLICT) {
            return;
        }

        foreach ($details as $index => $detail) {
            $categoryKey = trim((string) ($detail['category_key'] ?? ''));

            if (! in_array($categoryKey, $categories, true)) {
                throw new RuntimeException(sprintf(
                    'Rincian #%s memakai kategori "%s" yang tidak termasuk kategori terpilih.',
                    $index,
                    $categoryKey,
                ));
            }
        }
    }
}
