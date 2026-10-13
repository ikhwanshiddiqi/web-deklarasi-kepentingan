<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ConflictDeclaration;
use App\Models\ConflictDeclarationCategory;
use App\Models\ConflictDeclarationDetail;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DeclarationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature test penyimpanan deklarasi (Task 6.3).
 *
 * Memverifikasi:
 * - Penyimpanan atomik declaration + kategori + rincian (Req 4.11).
 * - Snapshot identitas karyawan diambil dari profil DB (Req 4.12).
 * - Label APPROVED + submitted_at tersetel; pdf_path null (Req 4.13).
 * - Status `none` tidak menulis kategori/rincian.
 * - FormRequest menjaga controller (payload tidak valid → tidak ada baris).
 * - Audit event declaration_submitted tercatat (Req 7.1).
 * - Invariant service menolak rincian berkategori asing (belt-and-suspenders Req 4.9).
 * - Owner check placeholder show (anti-IDOR sementara).
 *
 * Tambahan Task 6.4 (konsolidasi cakupan tanpa duplikasi):
 * - Penyimpanan banyak kategori + banyak rincian secara benar (Req 4.11).
 * - Req 4.5 di lapisan HTTP: `none` + rincian ditolak (bukan hanya validator).
 * - Req 4.9 di lapisan HTTP: rincian berkategori tidak terpilih ditolak.
 * - Snapshot imutabel: edit profil tidak mengubah snapshot deklarasi (Req 4.12).
 *
 * Requirements: 4.4, 4.5, 4.9, 4.11, 4.12, 4.13, 7.1
 */
class StoreDeclarationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sejak Task 7.2, store() memicu PdfService yang menulis ke disk privat
        // `local`. Fake storage agar tidak ada berkas nyata yang ditulis selama tes.
        Storage::fake('local');
    }

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

    public function test_valid_none_declaration_persists_snapshot_without_categories_or_details(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $response = $this->actingAs($user)->post('/declarations', [
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
        ]);

        // Satu deklarasi tersimpan; redirect ke halaman hasil.
        $this->assertSame(1, ConflictDeclaration::count());
        $declaration = ConflictDeclaration::firstOrFail();
        $response->assertRedirect(route('declarations.show', $declaration));

        // Status + snapshot identitas dari profil (Req 4.12).
        $this->assertSame(ConflictDeclaration::STATUS_NONE, $declaration->declaration_status);
        $this->assertSame($user->id, $declaration->user_id);
        $this->assertSame('Budi Santoso', $declaration->employee_name);
        $this->assertSame('1234567890123456', $declaration->employee_nik);
        $this->assertSame('Analis GRC', $declaration->employee_position);
        $this->assertSame('Unit Tata Kelola', $declaration->employee_work_unit);

        // Label + submitted_at tersetel (Req 4.13). PDF tidak disimpan saat submit;
        // dokumen dibuat hanya saat preview/unduh diminta.
        $this->assertSame(DeclarationService::APPROVED_LABEL, $declaration->approved_label);
        $this->assertNotNull($declaration->submitted_at);
        $this->assertNull($declaration->pdf_path);
        Storage::disk('local')->assertMissing('declarations/' . $declaration->id . '.pdf');

        // Tidak ada kategori/rincian untuk status none.
        $this->assertSame(0, ConflictDeclarationCategory::count());
        $this->assertSame(0, ConflictDeclarationDetail::count());

        // Audit submit tercatat (Req 7.1).
        $log = AuditLog::where('event', AuditService::EVENT_DECLARATION_SUBMITTED)->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(ConflictDeclaration::class, $log->target_type);
        $this->assertSame($declaration->id, (int) $log->target_id);
    }

    public function test_employee_cannot_submit_second_declaration_and_is_redirected_to_existing_one(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $first = $user->declarations()->create([
            'employee_name' => 'Budi Santoso',
            'employee_nik' => $user->nik,
            'employee_position' => 'Analis GRC',
            'employee_work_unit' => 'Unit Tata Kelola',
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
            'approved_label' => DeclarationService::APPROVED_LABEL,
            'submitted_at' => now('Asia/Jakarta'),
            'pdf_path' => null,
        ]);

        $response = $this->actingAs($user)->from('/declarations/create')->post('/declarations', [
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
        ]);

        $this->assertSame(1, ConflictDeclaration::count());
        $response->assertRedirect(route('declarations.edit', $first));
        $response->assertSessionHas('warning');
    }

    public function test_employee_can_edit_existing_declaration(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $declaration = $user->declarations()->create([
            'employee_name' => 'Budi Santoso',
            'employee_nik' => $user->nik,
            'employee_position' => 'Analis GRC',
            'employee_work_unit' => 'Unit Tata Kelola',
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
            'approved_label' => DeclarationService::APPROVED_LABEL,
            'submitted_at' => now('Asia/Jakarta'),
            'pdf_path' => null,
        ]);

        $response = $this->actingAs($user)->put(route('declarations.update', $declaration), [
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
            'categories' => ['family_relationship'],
            'details' => [[
                'category_key' => 'family_relationship',
                'related_party_name' => 'Andi',
                'situation_explanation' => 'Hubungan saudara kandung di unit yang sama.',
            ]],
        ]);

        $declaration->refresh();
        $response->assertRedirect(route('declarations.show', $declaration));
        $this->assertSame(ConflictDeclaration::STATUS_HAS_CONFLICT, $declaration->declaration_status);
        $this->assertSame(1, $declaration->categories()->count());
        $this->assertSame(1, $declaration->details()->count());
    }

    public function test_valid_has_conflict_persists_category_and_detail(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $response = $this->actingAs($user)->post('/declarations', [
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
            'categories' => ['family_relationship'],
            'details' => [
                [
                    'category_key' => 'family_relationship',
                    'related_party_name' => 'Andi',
                    'situation_explanation' => 'Hubungan saudara kandung di unit yang sama.',
                ],
            ],
        ]);

        $this->assertSame(1, ConflictDeclaration::count());
        $declaration = ConflictDeclaration::firstOrFail();
        $response->assertRedirect(route('declarations.show', $declaration));

        $this->assertSame(ConflictDeclaration::STATUS_HAS_CONFLICT, $declaration->declaration_status);
        $this->assertSame('Budi Santoso', $declaration->employee_name);

        // Tepat satu kategori + satu rincian, terhubung ke deklarasi yang benar.
        $this->assertSame(1, ConflictDeclarationCategory::count());
        $this->assertSame(1, ConflictDeclarationDetail::count());

        $category = ConflictDeclarationCategory::firstOrFail();
        $this->assertSame($declaration->id, $category->declaration_id);
        $this->assertSame('family_relationship', $category->category_key);

        $detail = ConflictDeclarationDetail::firstOrFail();
        $this->assertSame($declaration->id, $detail->declaration_id);
        $this->assertSame('family_relationship', $detail->category_key);
        $this->assertSame('Andi', $detail->related_party_name);
        $this->assertSame('Hubungan saudara kandung di unit yang sama.', $detail->situation_explanation);
    }

    public function test_invalid_payload_none_with_category_persists_nothing(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $response = $this->actingAs($user)
            ->from('/declarations/create')
            ->post('/declarations', [
                'declaration_status' => ConflictDeclaration::STATUS_NONE,
                'categories' => ['family_relationship'],
            ]);

        // FormRequest menjaga controller — validasi gagal, tidak ada yang tersimpan.
        $response->assertSessionHasErrors('categories');
        $this->assertSame(0, ConflictDeclaration::count());
        $this->assertSame(0, ConflictDeclarationCategory::count());
        $this->assertSame(0, ConflictDeclarationDetail::count());
        $this->assertSame(0, AuditLog::where('event', AuditService::EVENT_DECLARATION_SUBMITTED)->count());
    }

    public function test_service_invariant_rejects_detail_referencing_unselected_category_and_persists_nothing(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        // Lewati FormRequest dan panggil service langsung dengan payload yang
        // melanggar invariant (rincian memakai kategori yang tidak dipilih).
        $service = app(DeclarationService::class);

        try {
            $service->store($user, [
                'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
                'categories' => ['family_relationship'],
                'details' => [
                    [
                        'category_key' => 'procurement_finance',
                        'related_party_name' => 'PT Vendor',
                        'situation_explanation' => 'Terlibat seleksi vendor.',
                    ],
                ],
            ]);

            $this->fail('Service seharusnya melempar RuntimeException untuk rincian berkategori asing.');
        } catch (RuntimeException $e) {
            // Diharapkan.
        }

        // Tidak ada penulisan parsial yang bertahan (Req 4.11).
        $this->assertSame(0, ConflictDeclaration::count());
        $this->assertSame(0, ConflictDeclarationCategory::count());
        $this->assertSame(0, ConflictDeclarationDetail::count());
    }

    public function test_valid_has_conflict_persists_multiple_categories_and_details(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        // Dua kategori terpilih + tiga rincian tersebar di kedua kategori.
        // Menguji loop penyimpanan + transaksi dengan data realistis (Req 4.11).
        $response = $this->actingAs($user)->post('/declarations', [
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
            'categories' => ['family_relationship', 'procurement_finance'],
            'details' => [
                [
                    'category_key' => 'family_relationship',
                    'related_party_name' => 'Andi',
                    'situation_explanation' => 'Saudara kandung bekerja di unit yang sama.',
                ],
                [
                    'category_key' => 'procurement_finance',
                    'related_party_name' => 'PT Mitra Jaya',
                    'situation_explanation' => 'Terlibat seleksi vendor pengadaan.',
                ],
                [
                    'category_key' => 'procurement_finance',
                    'related_party_name' => 'CV Sumber Rezeki',
                    'situation_explanation' => 'Menyetujui transaksi keuangan dengan afiliasi.',
                ],
            ],
        ]);

        $this->assertSame(1, ConflictDeclaration::count());
        $declaration = ConflictDeclaration::firstOrFail();
        $response->assertRedirect(route('declarations.show', $declaration));

        // Seluruh kategori + rincian tersimpan dan terhubung ke deklarasi (Req 4.11).
        $this->assertSame(2, ConflictDeclarationCategory::count());
        $this->assertSame(3, ConflictDeclarationDetail::count());

        $this->assertEqualsCanonicalizing(
            ['family_relationship', 'procurement_finance'],
            $declaration->categories()->pluck('category_key')->all(),
        );

        $this->assertSame(
            1,
            $declaration->details()->where('category_key', 'family_relationship')->count(),
        );
        $this->assertSame(
            2,
            $declaration->details()->where('category_key', 'procurement_finance')->count(),
        );

        // Snapshot identitas tetap dari profil (Req 4.12).
        $this->assertSame('Budi Santoso', $declaration->employee_name);
        $this->assertSame('1234567890123456', $declaration->employee_nik);
        $this->assertSame('Analis GRC', $declaration->employee_position);
        $this->assertSame('Unit Tata Kelola', $declaration->employee_work_unit);
    }

    public function test_http_none_with_details_persists_nothing(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        // Req 4.5 di lapisan HTTP: status `none` + rincian terisi harus ditolak
        // FormRequest sehingga tidak ada baris yang tersimpan.
        $response = $this->actingAs($user)
            ->from('/declarations/create')
            ->post('/declarations', [
                'declaration_status' => ConflictDeclaration::STATUS_NONE,
                'details' => [
                    [
                        'category_key' => 'family_relationship',
                        'related_party_name' => 'Andi',
                        'situation_explanation' => 'Hubungan saudara kandung.',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('details');
        $this->assertSame(0, ConflictDeclaration::count());
        $this->assertSame(0, ConflictDeclarationCategory::count());
        $this->assertSame(0, ConflictDeclarationDetail::count());
        $this->assertSame(
            0,
            AuditLog::where('event', AuditService::EVENT_DECLARATION_SUBMITTED)->count(),
        );
    }

    public function test_http_detail_referencing_unselected_category_persists_nothing(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        // Req 4.9 di lapisan HTTP (bukan hanya service): rincian memakai kategori
        // valid namun TIDAK dicentang di Bagian B harus ditolak FormRequest.
        $response = $this->actingAs($user)
            ->from('/declarations/create')
            ->post('/declarations', [
                'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
                'categories' => ['family_relationship'],
                'details' => [
                    [
                        'category_key' => 'procurement_finance',
                        'related_party_name' => 'PT Vendor',
                        'situation_explanation' => 'Terlibat seleksi vendor.',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('details.0.category_key');
        $this->assertSame(0, ConflictDeclaration::count());
        $this->assertSame(0, ConflictDeclarationCategory::count());
        $this->assertSame(0, ConflictDeclarationDetail::count());
    }

    public function test_editing_profile_after_save_does_not_change_declaration_snapshot(): void
    {
        $user = $this->makeUser();
        $profile = $this->completeProfileFor($user);

        $this->actingAs($user)->post('/declarations', [
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
        ]);

        $declaration = ConflictDeclaration::firstOrFail();

        // Ubah profil setelah deklarasi tersimpan (Req 4.12 — snapshot imutabel).
        $profile->update([
            'full_name' => 'Budi Santoso Diperbarui',
            'position' => 'Manajer GRC',
            'work_unit' => 'Unit Baru',
        ]);

        // Muat ulang deklarasi dari DB; snapshot harus tetap seperti saat disimpan.
        $declaration->refresh();

        $this->assertSame('Budi Santoso', $declaration->employee_name);
        $this->assertSame('Analis GRC', $declaration->employee_position);
        $this->assertSame('Unit Tata Kelola', $declaration->employee_work_unit);
        $this->assertSame('1234567890123456', $declaration->employee_nik);
    }

    public function test_show_placeholder_blocks_other_users_declaration(): void
    {
        $owner = $this->makeUser(['email' => 'owner@sig.id', 'nik' => '1111111111111111']);
        $this->completeProfileFor($owner);

        $declaration = $owner->declarations()->create([
            'employee_name' => 'Owner',
            'employee_nik' => $owner->nik,
            'employee_position' => 'Analis',
            'employee_work_unit' => 'Unit',
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
            'approved_label' => DeclarationService::APPROVED_LABEL,
            'submitted_at' => now(),
        ]);

        $attacker = $this->makeUser(['email' => 'attacker@sig.id', 'nik' => '2222222222222222']);
        $this->completeProfileFor($attacker, ['user_id' => $attacker->id]);

        $response = $this->actingAs($attacker)->get(route('declarations.show', $declaration));
        $response->assertForbidden();

        // Pemilik tetap dapat mengakses.
        $this->actingAs($owner)
            ->get(route('declarations.show', $declaration))
            ->assertOk();
    }
}
