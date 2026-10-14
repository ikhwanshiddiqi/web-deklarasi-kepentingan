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
 * Test pembuatan PDF deklarasi (Task 7.2).
 *
 * Memverifikasi:
 * - generate() membuat berkas pada disk PRIVAT `local` di `declarations/{id}.pdf`,
 *   byte diawali `%PDF-`, dan `pdf_path` disetel (Req 5.1, 5.3).
 * - Pembuatan ulang menimpa berkas (idempoten) tanpa duplikat (Req 5.4).
 * - Berkas tidak ditaruh pada disk `public` (Req 5.3).
 * - Alur store controller: pdf_path terisi + audit `pdf_generated` tercatat (Req 5.1).
 * - Kegagalan PDF: deklarasi tetap tersimpan, pdf_path null, user diperingatkan (Req 5.4).
 *
 * Requirements: 5.1, 5.3, 5.4
 */
class PdfServiceTest extends TestCase
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

    public function test_generate_returns_pdf_bytes_without_saving_to_disk(): void
    {
        Storage::fake('local');

        $user = $this->makeUser();
        $this->completeProfileFor($user);
        $declaration = $this->makeDeclaration($user);

        $contents = app(PdfService::class)->generate($declaration);

        $this->assertStringStartsWith('%PDF-', $contents);
        $this->assertNull($declaration->fresh()->pdf_path);
        Storage::disk('local')->assertMissing('declarations/' . $declaration->id . '.pdf');
        Storage::disk('public')->assertMissing('declarations/' . $declaration->id . '.pdf');
    }

    public function test_generate_is_safe_when_called_multiple_times_without_persistence(): void
    {
        Storage::fake('local');

        $user = $this->makeUser();
        $this->completeProfileFor($user);
        $declaration = $this->makeDeclaration($user);

        $service = app(PdfService::class);
        $first = $service->generate($declaration);
        $second = $service->generate($declaration->fresh());

        $this->assertStringStartsWith('%PDF-', $first);
        $this->assertStringStartsWith('%PDF-', $second);
        $this->assertSame(0, count(Storage::disk('local')->allFiles('declarations')));
        $this->assertNull($declaration->fresh()->pdf_path);
    }

    public function test_generate_cached_rebuilds_when_declaration_content_changes_even_if_timestamp_matches(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $time = now('Asia/Jakarta')->startOfSecond();

        $declaration = $this->makeDeclaration($user, [
            'updated_at' => $time,
            'submitted_at' => $time,
        ]);

        $declaration->categories()->create(['category_key' => 'family_relationship']);
        $declaration->details()->create([
            'category_key' => 'family_relationship',
            'related_party_name' => 'Andi',
            'situation_explanation' => 'Versi lama',
        ]);

        $service = app(PdfService::class);
        $first = $service->generateCached($declaration);

        $declaration->forceFill([
            'updated_at' => $time,
            'submitted_at' => $time,
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
        ])->saveQuietly();

        $declaration->categories()->delete();
        $declaration->details()->delete();
        $declaration->categories()->create(['category_key' => 'financial_interest']);
        $declaration->details()->create([
            'category_key' => 'financial_interest',
            'related_party_name' => 'Budi',
            'situation_explanation' => 'Versi baru setelah edit',
        ]);

        $second = $service->generateCached($declaration->fresh());

        $this->assertNotSame($first, $second);
        $this->assertStringStartsWith('%PDF-', $first);
        $this->assertStringStartsWith('%PDF-', $second);
    }

    public function test_store_does_not_create_pdf_file_and_keeps_pdf_path_null(): void
    {
        Storage::fake('local');

        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $response = $this->actingAs($user)->post('/declarations', [
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
        ]);

        $declaration = ConflictDeclaration::firstOrFail();
        $response->assertRedirect(route('declarations.show', $declaration));
        $response->assertSessionHas('status');

        $this->assertNull($declaration->pdf_path);
        Storage::disk('local')->assertMissing('declarations/' . $declaration->id . '.pdf');

        $this->assertSame(
            1,
            AuditLog::where('event', AuditService::EVENT_DECLARATION_SUBMITTED)->count(),
        );
        $this->assertSame(
            0,
            AuditLog::where('event', AuditService::EVENT_PDF_GENERATED)->count(),
        );
    }

    public function test_download_response_uses_no_cache_headers_and_fresh_filename(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $declaration = $this->makeDeclaration($user, [
            'updated_at' => now('Asia/Jakarta')->subMinute(),
            'submitted_at' => now('Asia/Jakarta')->subMinute(),
        ]);

        $response = $this->actingAs($user)->get(route('declarations.pdf', $declaration));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertIsString($cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
        $this->assertStringContainsString('deklarasi-' . $declaration->id . '-', $response->headers->get('content-disposition'));
    }
}
