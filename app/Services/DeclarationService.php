<?php

namespace App\Services;

use App\Models\ConflictDeclaration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeclarationService
{
    public const APPROVED_LABEL = 'APPROVED';

    public function store(User $user, array $data): ConflictDeclaration
    {
        $status = (string) ($data['declaration_status'] ?? '');

        $categories = $status === ConflictDeclaration::STATUS_HAS_CONFLICT
            ? $this->uniqueCategories($data['categories'] ?? [])
            : [];
        $details = $status === ConflictDeclaration::STATUS_HAS_CONFLICT
            ? array_values($data['details'] ?? [])
            : [];

        $this->assertDetailsReferenceSelectedCategories($status, $categories, $details);

        return DB::transaction(function () use ($user, $status, $categories, $details): ConflictDeclaration {
            $profile = $user->profile()->first();

            if ($profile === null) {
                throw new RuntimeException(
                    'Profil karyawan tidak ditemukan; deklarasi tidak dapat disimpan.'
                );
            }

            $declaration = ConflictDeclaration::create([
                'user_id' => $user->id,
                'employee_name' => $profile->full_name,
                'employee_nik' => $user->nik,
                'employee_nip' => $user->nip,
                'employee_position' => $profile->position,
                'employee_work_unit' => $profile->work_unit,
                'declaration_status' => $status,
                'approved_label' => self::APPROVED_LABEL,
                'submitted_at' => Carbon::now('Asia/Jakarta'),
                'pdf_path' => null,
            ]);

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
                        'situation_explanation' => $this->sanitizeRichText((string) ($detail['situation_explanation'] ?? '')),
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
                        'situation_explanation' => $this->sanitizeRichText((string) ($detail['situation_explanation'] ?? '')),
                    ]);
                }
            }

            return $declaration->fresh()->load(['categories', 'details']);
        });
    }

    private function sanitizeRichText(string $value): string
    {
        return trim((string) strip_tags($value, '<p><br><ol><ul><li><strong><em><u><s><span><blockquote><a>'));
    }

    private function uniqueCategories(array $categories): array
    {
        $normalized = array_map(static fn($value): string => trim((string) $value), $categories);
        $normalized = array_filter($normalized, static fn(string $value): bool => $value !== '');

        return array_values(array_unique($normalized));
    }

    private function assertDetailsReferenceSelectedCategories(
        string $status,
        array $categories,
        array $details,
    ): void {
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
