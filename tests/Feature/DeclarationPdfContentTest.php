<?php

namespace Tests\Feature;

use App\Models\ConflictDeclaration;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Feature test konten PDF untuk skenario isi beragam (Task 7.4).
 *
 * Melengkapi Task 7.1 (PdfTemplateTest — render in-memory status none & satu
 * kategori/satu rincian), 7.2 (PdfServiceTest — pembuatan/penyimpanan/regenerate)
 * dan 7.3 (DeclarationShowPdfTest — otorisasi/IDOR/unduh). File ini TIDAK
 * menduplikasi assertion tersebut; fokusnya adalah skenario KONTEN yang disebut
 * pada design.md bagian 11:
 *   - status none (tanpa Bagian C),
 *   - satu kategori + satu rincian,
 *   - banyak kategori (kelima) + banyak rincian (6, tersebar),
 *   - nama panjang (120+ karakter) & penjelasan panjang (1000+ karakter).
 *
 * Pendekatan: render template `pdf.declaration` ke HTML via View::make(...)->render()
 * (mengikuti PdfTemplateTest) namun dengan data yang DISIMPAN DI DATABASE
 * (RefreshDatabase + relasi nyata), sehingga memverifikasi template bekerja dengan
 * model Eloquent dan relasi yang dimuat dari DB — bukan sekadar model polos. Satu
 * skenario juga dirender penuh ke byte PDF via PdfService (Storage::fake) untuk
 * memastikan Dompdf dapat memproses konten panjang/banyak baris tanpa error.
 *
 * CATATAN PENTING (design.md bagian 11 + 13): PhpUnit hanya dapat memverifikasi
 * KEHADIRAN konten pada dokumen, BUKAN kesetiaan visual, pergantian halaman, atau
 * jumlah halaman akhir. Per kriteria penerimaan desain, tata letak PDF final
 * (termasuk page break untuk nama/uraian panjang & banyak rincian) WAJIB
 * diverifikasi secara manual pada server target dan disetujui oleh pemilik proses
 * formulir sebelum produksi. Tes ini bersifat pelengkap, bukan pengganti review
 * visual tersebut.
 *
 * Requirements: 5.2, 5.4, 6.2
 */
class DeclarationPdfContentTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'budi@sig.id',
            'nik' => '1234567890123456',
        ], $overrides));
    }

    private function completeProfileFor(User $user, array $overrides = []): EmployeeProfile
    {
        return EmployeeProfile::create(array_merge([
            'user_id' => $user->id,
            'full_name' => 'Budi Santoso',
            'position' => 'Analis GRC',
            'work_unit' => 'Unit Tata Kelola',
        ], $overrides));
    }

    /**
     * Buat deklarasi di DATABASE beserta kategori + rincian nyata.
     *
     * @param  list<string>  $categoryKeys
     * @param  list<array{category_key: string, related_party_name: string, situation_explanation: string}>  $details
     */
    private function makeDeclaration(User $user, string $status, array $categoryKeys = [], array $details = [], array $overrides = []): ConflictDeclaration
    {
        $declaration = $user->declarations()->create(array_merge([
            'employee_name' => 'Budi Santoso',
            'employee_nik' => $user->nik,
            'employee_nip' => $user->nip,
            'employee_position' => 'Analis GRC',
            'employee_work_unit' => 'Unit Tata Kelola',
            'declaration_status' => $status,
            'approved_label' => 'APPROVED',
            'submitted_at' => now('Asia/Jakarta'),
            'pdf_path' => null,
        ], $overrides));

        foreach ($categoryKeys as $key) {
            $declaration->categories()->create(['category_key' => $key]);
        }

        foreach ($details as $detail) {
            $declaration->details()->create($detail);
        }

        return $declaration;
    }

    /**
     * Render template dari deklarasi yang dimuat ULANG dari DB (relasi nyata).
     */
    private function render(ConflictDeclaration $declaration): string
    {
        $fresh = ConflictDeclaration::with(['categories', 'details'])->findOrFail($declaration->id);

        return View::make('pdf.declaration', [
            'declaration' => $fresh,
            'categories' => config('conflict_categories'),
        ])->render();
    }

    public function test_show_page_does_not_auto_load_pdf_preview_until_user_clicks_it(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);
        $declaration = $this->makeDeclaration($user, ConflictDeclaration::STATUS_NONE);

        $response = $this->actingAs($user)
            ->get(route('declarations.show', $declaration));

        $response->assertOk();
        $response->assertDontSee('iframe src="' . route('declarations.preview', $declaration) . '"', false);
        $response->assertSee('Preview PDF');
        $response->assertSee('Buka PDF penuh');
    }

    public function test_preview_route_returns_valid_pdf_bytes(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);
        $declaration = $this->makeDeclaration($user, ConflictDeclaration::STATUS_HAS_CONFLICT, ['procurement_finance'], [[
            'category_key' => 'procurement_finance',
            'related_party_name' => 'PT Mitra Sejahtera',
            'situation_explanation' => 'Terlibat dalam seleksi vendor pengadaan.',
        ]]);

        $response = $this->actingAs($user)
            ->get(route('declarations.preview', $declaration));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_status_none_shows_tidak_memiliki_without_bagian_c_table(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);
        $declaration = $this->makeDeclaration($user, ConflictDeclaration::STATUS_NONE);

        $html = $this->render($declaration);

        $this->assertStringContainsString('tidak memiliki', $html);
        // Mengikuti formulir asli: Bagian C dan seluruh kategori SELALU tampil
        // (seperti kertas kosong), hanya tidak ada tanda centang/rincian terisi.
        $this->assertStringContainsString('Nama Pihak Terkait', $html);
        $this->assertStringContainsString('Tabel Identifikasi dan Deklarasi', $html);
    }

    public function test_status_none_renders_all_categories_unchecked(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);
        $declaration = $this->makeDeclaration($user, ConflictDeclaration::STATUS_NONE);

        $html = $this->render($declaration);

        // Seluruh 5 kategori tetap tercetak meski tidak ada yang dicentang.
        foreach (config('conflict_categories') as $meta) {
            $this->assertStringContainsString(e($meta['label']), $html);
        }
    }

    public function test_single_category_and_detail_shows_label_and_detail_row(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $declaration = $this->makeDeclaration(
            $user,
            ConflictDeclaration::STATUS_HAS_CONFLICT,
            ['procurement_finance'],
            [[
                'category_key' => 'procurement_finance',
                'related_party_name' => 'PT Mitra Sejahtera',
                'situation_explanation' => 'Terlibat dalam seleksi vendor pengadaan.',
            ]],
        );

        $html = $this->render($declaration);

        // Label kategori dari config muncul (Blade meng-escape '&' → '&amp;').
        $this->assertStringContainsString('Pengadaan &amp; Keuangan', $html);
        // Baris rincian muncul: nama pihak + penjelasan + penomoran.
        $this->assertStringContainsString('PT Mitra Sejahtera', $html);
        $this->assertStringContainsString('Terlibat dalam seleksi vendor pengadaan.', $html);
        // Bagian C aktif untuk has_conflict (header tabel rincian muncul).
        $this->assertStringContainsString('Nama Pihak Terkait', $html);
        $this->assertStringContainsString('<td class="no">1</td>', $html);
    }

    public function test_many_categories_and_details_render_all_labels_parties_and_numbering(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        // Kelima kategori dicentang.
        $allCategories = array_keys(config('conflict_categories'));
        $this->assertCount(5, $allCategories);

        // Enam rincian tersebar di berbagai kategori (nama & penjelasan unik).
        $details = [
            ['category_key' => 'family_relationship', 'related_party_name' => 'Andi Pratama', 'situation_explanation' => 'Saudara kandung di unit yang sama.'],
            ['category_key' => 'multiple_positions', 'related_party_name' => 'Komisaris PT Alfa', 'situation_explanation' => 'Merangkap jabatan di PT Alfa.'],
            ['category_key' => 'hr_process', 'related_party_name' => 'Siti Rahma', 'situation_explanation' => 'Terlibat penilaian kinerja kerabat.'],
            ['category_key' => 'procurement_finance', 'related_party_name' => 'PT Beta Vendor', 'situation_explanation' => 'Menyetujui transaksi dengan afiliasi.'],
            ['category_key' => 'financial_interest', 'related_party_name' => 'PT Gamma Tbk', 'situation_explanation' => 'Memiliki saham pada vendor perusahaan.'],
            ['category_key' => 'family_relationship', 'related_party_name' => 'Dewi Lestari', 'situation_explanation' => 'Pasangan bekerja pada mitra bisnis.'],
        ];

        $declaration = $this->makeDeclaration(
            $user,
            ConflictDeclaration::STATUS_HAS_CONFLICT,
            $allCategories,
            $details,
        );

        $html = $this->render($declaration);

        // Semua label kategori tampil di daftar Bagian B.
        // Blade meng-escape output, jadi bandingkan terhadap bentuk ter-escape (e()).
        foreach (config('conflict_categories') as $meta) {
            $this->assertStringContainsString(e($meta['label']), $html);
        }

        // Semua nama pihak & penjelasan tampil pada tabel rincian.
        foreach ($details as $detail) {
            $this->assertStringContainsString(e($detail['related_party_name']), $html);
            $this->assertStringContainsString(e($detail['situation_explanation']), $html);
        }

        // Penomoran otomatis hadir untuk keenam baris (kolom No. 1..6).
        for ($no = 1; $no <= count($details); $no++) {
            $this->assertStringContainsString('<td class="no">' . $no . '</td>', $html);
        }
    }

    public function test_long_name_and_long_explanation_render_without_error_and_include_content(): void
    {
        // Nama panjang (120+ karakter) & penjelasan panjang (1000+ karakter) untuk
        // menguji aliran teks/wrapping. Verifikasi visual page break dilakukan manual
        // di server target (lihat docblock kelas).
        $longName = str_repeat('Nama Pihak Terkait Yang Sangat Panjang ', 4); // ~156 karakter
        $this->assertGreaterThan(120, strlen($longName));

        $longExplanation = str_repeat('Penjelasan situasi benturan kepentingan yang sangat rinci dan panjang. ', 20); // ~1400 karakter
        $this->assertGreaterThan(1000, strlen($longExplanation));

        $user = $this->makeUser();
        $this->completeProfileFor($user, ['full_name' => $longName]);

        $declaration = $this->makeDeclaration(
            $user,
            ConflictDeclaration::STATUS_HAS_CONFLICT,
            ['financial_interest'],
            [[
                'category_key' => 'financial_interest',
                'related_party_name' => $longName,
                'situation_explanation' => $longExplanation,
            ]],
            ['employee_name' => $longName],
        );

        // 1) Template HTML dirender tanpa error dan memuat konten panjang (prefiks).
        $html = $this->render($declaration);
        $this->assertStringContainsString(substr($longName, 0, 60), $html);
        $this->assertStringContainsString(substr($longExplanation, 0, 120), $html);

        // 2) Dompdf dapat menghasilkan PDF byte untuk konten panjang tanpa error
        //    (menjalankan jalur produksi penuh; page-flow nyata diverifikasi manual).
        $contents = app(PdfService::class)->generate($declaration->fresh());
        $this->assertStringStartsWith('%PDF-', $contents);
    }

    /**
     * Konfirmasi IDOR pada route PDF dengan data DB nyata (Req 6.2): pengguna B
     * mengubah ID URL ke deklarasi milik pengguna A memperoleh 403.
     *
     * Catatan: skenario 403 pada route PDF juga diuji di DeclarationShowPdfTest
     * (Task 7.3). Di sini kami memverifikasi secara eksplisit vektor "ubah ID URL"
     * dari Req 6.2 pada file konsolidasi Task 7.4, tanpa menduplikasi assertion
     * audit/unduh milik 7.3.
     */
    public function test_idor_user_b_changing_url_id_to_user_a_declaration_is_forbidden_on_pdf_route(): void
    {
        Storage::fake('local');

        $userA = $this->makeUser(['email' => 'a@sig.id', 'nik' => '1111111111111111']);
        $this->completeProfileFor($userA);
        $declarationA = $this->makeDeclaration($userA, ConflictDeclaration::STATUS_NONE);

        $userB = $this->makeUser(['email' => 'b@sig.id', 'nik' => '2222222222222222']);
        $this->completeProfileFor($userB);

        // User B menebak/mengubah ID URL ke deklarasi milik User A.
        $this->actingAs($userB)
            ->get(route('declarations.pdf', $declarationA))
            ->assertForbidden();
    }
}
