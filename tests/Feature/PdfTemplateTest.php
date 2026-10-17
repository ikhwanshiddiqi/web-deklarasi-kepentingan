<?php

namespace Tests\Feature;

use App\Models\ConflictDeclaration;
use App\Models\ConflictDeclarationCategory;
use App\Models\ConflictDeclarationDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Verifikasi template cetak PDF A4 `pdf.declaration` (Task 7.1).
 *
 * Template dirender ke string HTML memakai model in-memory (tanpa DB) sehingga
 * tes cepat dan tidak bergantung pada koneksi database. Relasi categories/details
 * disetel via setRelation(). Hanya memeriksa string kunci wajib yang harus muncul
 * pada dokumen (judul, kode dokumen, pernyataan, APPROVED, label kategori, dsb).
 *
 * Requirements: 5.2
 */
class PdfTemplateTest extends TestCase
{
    /**
     * Bangun deklarasi in-memory beserta relasi tanpa menyentuh database.
     *
     * @param  list<string>  $categoryKeys
     * @param  list<array{category_key: string, related_party_name: string, situation_explanation: string}>  $details
     */
    private function makeDeclaration(string $status, array $categoryKeys = [], array $details = []): ConflictDeclaration
    {
        $declaration = new ConflictDeclaration([
            'employee_name' => 'Budi Santoso',
            'employee_nik' => '1234567890123456',
            'employee_position' => 'Analis GRC',
            'employee_work_unit' => 'Unit Tata Kelola',
            'declaration_status' => $status,
            'approved_label' => 'APPROVED',
        ]);
        $declaration->submitted_at = Carbon::create(2024, 3, 15, 10, 0, 0, 'Asia/Jakarta');

        $declaration->setRelation(
            'categories',
            collect($categoryKeys)->map(fn(string $key) => new ConflictDeclarationCategory(['category_key' => $key]))
        );

        $declaration->setRelation(
            'details',
            collect($details)->map(fn(array $d) => new ConflictDeclarationDetail($d))
        );

        return $declaration;
    }

    private function render(ConflictDeclaration $declaration): string
    {
        return View::make('pdf.declaration', [
            'declaration' => $declaration,
            'categories' => config('conflict_categories'),
        ])->render();
    }

    public function test_render_memuat_elemen_wajib_untuk_status_has_conflict(): void
    {
        $declaration = $this->makeDeclaration(
            ConflictDeclaration::STATUS_HAS_CONFLICT,
            ['family_relationship'],
            [
                [
                    'category_key' => 'family_relationship',
                    'related_party_name' => 'Andi Pratama',
                    'situation_explanation' => 'Saudara kandung bekerja di unit yang sama.',
                ],
            ],
        );

        $html = $this->render($declaration);

        // Judul formulir resmi.
        $this->assertStringContainsString('PERNYATAAN BENTURAN KEPENTINGAN', $html);
        // Kode dokumen + revisi (footer & header).
        $this->assertStringContainsString('FI/SP/GRC/973', $html);
        $this->assertStringContainsString('Rev. 01', $html);
        // Pernyataan tetap.
        $this->assertStringContainsString(
            'Saya menyatakan bahwa seluruh informasi ini adalah benar dan lengkap',
            $html
        );
        // APPROVED hijau + nama karyawan (snapshot).
        $this->assertStringContainsString('APPROVED', $html);
        $this->assertStringContainsString('#15803d', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        // Data karyawan snapshot.
        $this->assertStringContainsString('1234567890123456', $html);
        $this->assertStringContainsString('Analis GRC', $html);
        $this->assertStringContainsString('Unit Tata Kelola', $html);
        // Opsi status "memiliki" tercetak dan label kategori selalu ada.
        $this->assertStringContainsString('memiliki', $html);
        $this->assertStringContainsString('Hubungan keluarga', $html);
        // Rincian Bagian C.
        $this->assertStringContainsString('Andi Pratama', $html);
        $this->assertStringContainsString('Saudara kandung bekerja di unit yang sama.', $html);
        // Tanggal dalam zona Asia/Jakarta.
        $this->assertStringContainsString('2024', $html);
    }

    public function test_render_status_none_menampilkan_tidak_memiliki_tanpa_tabel_rincian(): void
    {
        $declaration = $this->makeDeclaration(ConflictDeclaration::STATUS_NONE);

        $html = $this->render($declaration);

        $this->assertStringContainsString('tidak memiliki', $html);
        $this->assertStringContainsString('APPROVED', $html);
        $this->assertStringContainsString('FI/SP/GRC/973', $html);
        // Mengikuti formulir asli: Bagian C + seluruh kategori SELALU tampil,
        // meski status "tidak memiliki". Header tabel Bagian C tetap ada.
        $this->assertStringContainsString('Nama Pihak Terkait', $html);
        // Seluruh 5 kategori tetap tercetak (tidak dihapus walau tak dicentang).
        $this->assertStringContainsString('Hubungan keluarga', $html);
        $this->assertStringContainsString('Kepentingan finansial/investasi', $html);
    }

    public function test_render_memecah_tabel_ke_beberapa_halaman_dengan_header_ulang(): void
    {
        $details = [];
        for ($i = 0; $i < 12; $i++) {
            $details[] = [
                'category_key' => 'family_relationship',
                'related_party_name' => 'Pihak ' . ($i + 1),
                'situation_explanation' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. ' . ($i + 1),
            ];
        }

        $declaration = $this->makeDeclaration(
            ConflictDeclaration::STATUS_HAS_CONFLICT,
            ['family_relationship'],
            $details,
        );

        $html = $this->render($declaration);

        $this->assertSame(2, substr_count($html, 'Kondisi yang dicentang'));
    }
}
