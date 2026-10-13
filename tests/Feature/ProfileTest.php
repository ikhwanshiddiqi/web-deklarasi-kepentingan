<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test Setting Akun — profil karyawan (Requirement 3).
 *
 * Mencakup: guard auth, tampilan email/NIK dari DB, create-or-update profil,
 * penandaan kelengkapan, audit perubahan, validasi field wajib, dan jaminan
 * email/NIK tidak dapat diubah lewat endpoint ini.
 *
 * Requirements: 3.1, 3.2, 3.3, 3.5
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'budi@sig.id',
            'nik' => '1234567890123456',
        ], $overrides));
    }

    public function test_tamu_diarahkan_ke_login_dari_setting_akun(): void
    {
        $this->get('/account/settings')->assertRedirect('/login');
    }

    public function test_pengguna_terautentikasi_melihat_email_dan_nik_dari_database(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/account/settings');

        $response->assertOk();
        $response->assertSee('budi@sig.id');
        $response->assertSee('1234567890123456');
        // Profil belum diisi → badge "Belum lengkap" (Req 3.3).
        $response->assertSee('Belum lengkap');
    }

    public function test_menyimpan_profil_valid_membuat_profil_dan_menandai_lengkap(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->put('/account/settings', [
            'full_name' => 'Budi Santoso',
            'position' => 'Analis GRC',
            'work_unit' => 'Unit Tata Kelola',
        ]);

        $response->assertRedirect('/account/settings');
        $response->assertSessionHas('status');

        // Profil tersimpan (create-or-update) terhubung ke user login (Req 3.5).
        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $user->id,
            'full_name' => 'Budi Santoso',
            'position' => 'Analis GRC',
            'work_unit' => 'Unit Tata Kelola',
        ]);

        // Profil kini lengkap (Req 3.3).
        $this->assertTrue($user->fresh()->hasCompleteProfile());

        // Audit perubahan profil tercatat: tepat satu baris profile_updated
        // dengan pelaku = user login dan sasaran = EmployeeProfile (Req 7.1, 7.2).
        $profile = EmployeeProfile::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(
            1,
            AuditLog::where('event', AuditService::EVENT_PROFILE_UPDATED)->count(),
            'Satu update profil harus menulis tepat satu baris audit profile_updated.',
        );

        $log = AuditLog::where('event', AuditService::EVENT_PROFILE_UPDATED)->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(EmployeeProfile::class, $log->target_type);
        $this->assertSame($profile->id, (int) $log->target_id);
        $this->assertNotNull($log->created_at);
    }

    public function test_badge_status_berubah_menjadi_lengkap_setelah_update_valid(): void
    {
        $user = $this->makeUser();

        // Sebelum update: badge menampilkan "Belum lengkap" (Req 3.3).
        $this->actingAs($user)->get('/account/settings')->assertSee('Belum lengkap');

        $this->actingAs($user)->put('/account/settings', [
            'full_name' => 'Budi Santoso',
            'position' => 'Analis GRC',
            'work_unit' => 'Unit Tata Kelola',
        ])->assertRedirect('/account/settings');

        // Setelah update: badge menampilkan "Lengkap" dan bukan "Belum lengkap" (Req 3.3).
        // Gunakan instance user baru agar relasi profil dimuat ulang dari DB,
        // meniru request HTTP terpisah (bukan objek Eloquent yang sama di memori).
        $response = $this->actingAs($user->fresh())->get('/account/settings');
        $response->assertOk();
        $response->assertSee('Lengkap');
        $response->assertDontSee('Belum lengkap');
    }

    public function test_baris_audit_update_profil_tidak_memuat_data_sensitif(): void
    {
        $user = $this->makeUser();
        $password = 'SangatRahasia987';

        $this->actingAs($user)->put('/account/settings', [
            'full_name' => 'Budi Santoso',
            'position' => 'Analis GRC',
            'work_unit' => 'Unit Tata Kelola',
            // Payload jahat: coba selipkan field sensitif.
            'password' => $password,
        ])->assertRedirect('/account/settings');

        $log = AuditLog::where('event', AuditService::EVENT_PROFILE_UPDATED)->firstOrFail();

        // Atribut baris audit hanya kolom yang diharapkan — tidak ada payload bebas (Req 7.3).
        $this->assertEqualsCanonicalizing(
            ['id', 'user_id', 'event', 'target_type', 'target_id', 'created_at'],
            array_keys($log->getAttributes()),
        );

        // Tidak ada password plaintext yang bocor ke baris audit (Req 7.3).
        $serialized = json_encode($log->getAttributes());
        $this->assertStringNotContainsString($password, $serialized);
    }

    public function test_menyimpan_profil_memperbarui_profil_yang_sudah_ada(): void
    {
        $user = $this->makeUser();
        EmployeeProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Nama Lama',
            'position' => 'Posisi Lama',
            'work_unit' => 'Unit Lama',
        ]);

        $this->actingAs($user)->put('/account/settings', [
            'full_name' => 'Nama Baru',
            'position' => 'Posisi Baru',
            'work_unit' => 'Unit Baru',
        ])->assertRedirect('/account/settings');

        // Tidak membuat baris baru; memperbarui yang ada (FK unik user_id).
        $this->assertSame(1, EmployeeProfile::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $user->id,
            'full_name' => 'Nama Baru',
            'position' => 'Posisi Baru',
            'work_unit' => 'Unit Baru',
        ]);
    }

    public function test_validasi_field_wajib_mengembalikan_error_dan_tidak_menyimpan(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->from('/account/settings')
            ->put('/account/settings', [
                'full_name' => '',
                'position' => '',
                'work_unit' => '',
            ]);

        $response->assertRedirect('/account/settings');
        $response->assertSessionHasErrors(['full_name', 'position', 'work_unit']);

        // Tidak ada profil tersimpan.
        $this->assertDatabaseMissing('employee_profiles', [
            'user_id' => $user->id,
        ]);
    }

    public function test_email_dan_nik_tidak_dapat_diubah_lewat_endpoint_update(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->put('/account/settings', [
            'full_name' => 'Budi Santoso',
            'position' => 'Analis GRC',
            'work_unit' => 'Unit Tata Kelola',
            // Payload jahat: coba ubah email & NIK.
            'email' => 'penyusup@sig.id',
            'nik' => '9999999999999999',
        ])->assertRedirect('/account/settings');

        $fresh = $user->fresh();

        // Email & NIK tetap seperti semula (Req 3.1).
        $this->assertSame('budi@sig.id', $fresh->email);
        $this->assertSame('1234567890123456', $fresh->nik);
    }
}
