<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ConflictDeclaration;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature test halaman hasil deklarasi + unduh PDF berotorisasi (Task 7.3).
 *
 * Memverifikasi:
 * - Pemilik dapat melihat halaman hasil (200) + tombol Unduh PDF (Req 6.1).
 * - Non-pemilik ditolak 403 pada halaman hasil — IDOR dicegah (Req 6.2).
 * - Admin dapat melihat deklarasi user lain (design.md bagian 8).
 * - Pemilik dapat mengunduh PDF (attachment) dan event pdf_downloaded tercatat (Req 5.5, 7.1).
 * - Non-pemilik ditolak 403 pada route PDF (Req 5.5, 6.4).
 * - Bila pdf_path null, route PDF membuat ulang berkas lalu mengunduh (Req 5.4),
 *   dan event pdf_generated tercatat.
 * - Guest dialihkan ke login untuk kedua route (Req 6.1).
 *
 * Requirements: 5.5, 6.1, 6.2, 6.4, 7.1
 */
class DeclarationShowPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // PDF ditulis ke disk privat `local`; fake agar tidak ada berkas nyata.
        Storage::fake('local');
    }

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create($overrides);
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

    private function makeDeclaration(User $user, array $overrides = []): ConflictDeclaration
    {
        return $user->declarations()->create(array_merge([
            'employee_name' => 'Budi Santoso',
            'employee_nik' => $user->nik,
            'employee_position' => 'Analis GRC',
            'employee_work_unit' => 'Unit Tata Kelola',
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
            'approved_label' => 'APPROVED',
            'submitted_at' => now('Asia/Jakarta'),
            'pdf_path' => null,
        ], $overrides));
    }

    public function test_owner_can_view_results_page_with_download_button(): void
    {
        $owner = $this->makeUser(['email' => 'owner@sig.id', 'nik' => '1111111111111111']);
        $this->completeProfileFor($owner);

        // PDF sudah ada agar tombol "Unduh PDF" tampil.
        $declaration = $this->makeDeclaration($owner, ['pdf_path' => 'declarations/1.pdf']);

        $response = $this->actingAs($owner)->get(route('declarations.show', $declaration));

        $response->assertOk();
        $response->assertSee('Hasil Deklarasi');
        $response->assertSee('Tidak memiliki potensi benturan kepentingan');
        $response->assertSee('Unduh PDF');
        $response->assertSee(route('declarations.pdf', $declaration));
        $response->assertSee(route('declarations.preview', $declaration));
    }

    public function test_preview_route_returns_inline_pdf_for_authorized_user(): void
    {
        $owner = $this->makeUser(['email' => 'owner@sig.id', 'nik' => '1111111111111111']);
        $this->completeProfileFor($owner);
        $declaration = $this->makeDeclaration($owner);
        app(PdfService::class)->generate($declaration);

        $response = $this->actingAs($owner)->get(route('declarations.preview', $declaration));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', strtolower((string) $response->headers->get('content-disposition')));
    }

    public function test_non_owner_gets_403_on_results_page(): void
    {
        $owner = $this->makeUser(['email' => 'owner@sig.id', 'nik' => '1111111111111111']);
        $this->completeProfileFor($owner);
        $declaration = $this->makeDeclaration($owner);

        $attacker = $this->makeUser(['email' => 'attacker@sig.id', 'nik' => '2222222222222222']);
        $this->completeProfileFor($attacker);

        $this->actingAs($attacker)
            ->get(route('declarations.show', $declaration))
            ->assertForbidden();
    }

    public function test_admin_can_view_another_users_declaration(): void
    {
        $owner = $this->makeUser(['email' => 'owner@sig.id', 'nik' => '1111111111111111']);
        $this->completeProfileFor($owner);
        $declaration = $this->makeDeclaration($owner);

        $admin = $this->makeUser(['email' => 'admin@sig.id', 'nik' => '3333333333333333', 'role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('declarations.show', $declaration))
            ->assertOk();
    }

    public function test_owner_can_download_pdf_and_download_is_audited(): void
    {
        $owner = $this->makeUser(['email' => 'owner@sig.id', 'nik' => '1111111111111111']);
        $this->completeProfileFor($owner);
        $declaration = $this->makeDeclaration($owner);

        // Siapkan berkas PDF yang sudah ada pada disk privat.
        app(PdfService::class)->generate($declaration);
        $declaration->refresh();

        $response = $this->actingAs($owner)->get(route('declarations.pdf', $declaration));

        $response->assertOk();
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringContainsString(
            'deklarasi-' . $declaration->id . '.pdf',
            (string) $response->headers->get('content-disposition'),
        );

        // Event pdf_downloaded tercatat (Req 7.1).
        $log = AuditLog::where('event', AuditService::EVENT_PDF_DOWNLOADED)->firstOrFail();
        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame(ConflictDeclaration::class, $log->target_type);
        $this->assertSame($declaration->id, (int) $log->target_id);
    }

    public function test_non_owner_gets_403_on_pdf_route(): void
    {
        $owner = $this->makeUser(['email' => 'owner@sig.id', 'nik' => '1111111111111111']);
        $this->completeProfileFor($owner);
        $declaration = $this->makeDeclaration($owner);
        app(PdfService::class)->generate($declaration);

        $attacker = $this->makeUser(['email' => 'attacker@sig.id', 'nik' => '2222222222222222']);
        $this->completeProfileFor($attacker);

        $this->actingAs($attacker)
            ->get(route('declarations.pdf', $declaration->fresh()))
            ->assertForbidden();

        // Tidak ada event unduhan untuk upaya yang ditolak.
        $this->assertSame(
            0,
            AuditLog::where('event', AuditService::EVENT_PDF_DOWNLOADED)->count(),
        );
    }

    public function test_pdf_route_generates_on_demand_without_persisting_file(): void
    {
        $owner = $this->makeUser(['email' => 'owner@sig.id', 'nik' => '1111111111111111']);
        $this->completeProfileFor($owner);

        $declaration = $this->makeDeclaration($owner, ['pdf_path' => null]);

        $response = $this->actingAs($owner)->get(route('declarations.pdf', $declaration));

        $response->assertOk();
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('content-disposition'),
        );

        $this->assertNull($declaration->fresh()->pdf_path);
        Storage::disk('local')->assertMissing('declarations/' . $declaration->id . '.pdf');

        $this->assertSame(
            1,
            AuditLog::where('event', AuditService::EVENT_PDF_GENERATED)->count(),
        );
        $this->assertSame(
            1,
            AuditLog::where('event', AuditService::EVENT_PDF_DOWNLOADED)->count(),
        );
    }

    public function test_guest_is_redirected_to_login_for_both_routes(): void
    {
        $owner = $this->makeUser(['email' => 'owner@sig.id', 'nik' => '1111111111111111']);
        $this->completeProfileFor($owner);
        $declaration = $this->makeDeclaration($owner);

        $this->get(route('declarations.show', $declaration))->assertRedirect(route('login'));
        $this->get(route('declarations.pdf', $declaration))->assertRedirect(route('login'));
    }
}
