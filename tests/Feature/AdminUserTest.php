<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ConflictDeclaration;
use App\Models\ConflictDeclarationDetail;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test area admin — pengelolaan status akun (Requirement 8).
 *
 * Mencakup:
 * - Pembatasan akses role admin via middleware role:admin (Req 8.3):
 *   non-admin (employee) ditolak 403; tamu dialihkan ke login.
 * - Admin dapat melihat daftar akun (Req 8.1).
 * - Admin dapat mengaktifkan/menonaktifkan akun lain (Req 8.1).
 * - Guard: admin tidak dapat menonaktifkan akunnya sendiri.
 * - Perubahan status akun tercatat audit (Req 7.1) tanpa data sensitif (Req 7.3).
 * - Perubahan status akun TIDAK mengubah isi deklarasi (Req 8.2).
 *
 * Requirements: 7.1, 8.1, 8.2, 8.3
 */
class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function makeEmployee(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
    }

    public function test_non_admin_ditolak_dari_daftar_akun(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_admin_tidak_bisa_mengakses_form_deklarasi_employee(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('declarations.create'))
            ->assertForbidden();
    }

    public function test_non_admin_ditolak_dari_ubah_status(): void
    {
        $employee = $this->makeEmployee();
        $target = $this->makeEmployee();

        $this->actingAs($employee)
            ->put("/admin/users/{$target->id}/status", ['is_active' => false])
            ->assertForbidden();

        // Pastikan status target tidak berubah.
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_admin_dapat_melihat_daftar_akun(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin@sig.id']);
        $employee = $this->makeEmployee(['email' => 'karyawan@sig.id']);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $response->assertSee('admin@sig.id');
        $response->assertSee('karyawan@sig.id');
    }

    public function test_daftar_akun_menampilkan_nomor_dan_pagination_per_halaman(): void
    {
        $admin = $this->makeAdmin();

        User::factory()->count(25)->create(['role' => 'employee', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->get(route('admin.users.index', ['per_page' => 25]));

        $response->assertOk();
        $response->assertSee('No');
        $response->assertSee('Tampil');
        $response->assertSee('25');
        $response->assertSee('Menampilkan 1-25 dari');
        $response->assertSee('per halaman');
    }

    public function test_employee_mempunyai_home_route_dan_root_redirect_ke_home(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee)
            ->get('/home')
            ->assertOk()
            ->assertSee('Selamat datang');

        $this->actingAs($employee)
            ->get('/')
            ->assertRedirect(route('home'));
    }

    public function test_login_employee_dan_admin_dialihkan_ke_beranda_masing_masing(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee();

        $this->post('/login', [
            'identity' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.home'));

        $this->post('/login', [
            'identity' => $employee->email,
            'password' => 'password',
        ])->assertRedirect(route('home'));
    }

    public function test_halaman_auth_dan_logout_mengirim_header_no_store(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee)
            ->get(route('home'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');

        $this->actingAs($employee)
            ->post(route('logout'))
            ->assertRedirect('/login');
    }

    public function test_admin_header_menyembunyikan_menu_isi_form_dan_employee_masih_melihatnya(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('Isi Form');

        $this->actingAs($employee)
            ->get(route('account.settings'))
            ->assertOk()
            ->assertSee('Isi Form');
    }

    public function test_admin_dapat_melihat_daftar_data_deklarasi_yang_sudah_diisi(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee([
            'email' => 'karyawan@sig.id',
            'nik' => '1234567890123456',
        ]);

        $declaration = ConflictDeclaration::create([
            'user_id' => $employee->id,
            'employee_name' => 'Budi Santoso',
            'employee_nik' => '1234567890123456',
            'employee_position' => 'Analis GRC',
            'employee_work_unit' => 'Unit Tata Kelola',
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
            'approved_label' => 'APPROVED',
            'submitted_at' => now(),
            'pdf_path' => 'declarations/' . 1 . '.pdf',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.data.index', ['per_page' => 25]));

        $response->assertOk();
        $response->assertSee('Data');
        $response->assertSee('Menampilkan');
        $response->assertSee('25');
        $response->assertSee('Budi Santoso');
        $response->assertSee('Lihat detail deklarasi');
        $response->assertSee(route('declarations.show', $declaration));
        $response->assertSee('Unduh PDF deklarasi');
        $response->assertSee(route('declarations.pdf', $declaration));
    }

    public function test_admin_dapat_mengekspor_bundle_deklarasi(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee();

        $declaration = ConflictDeclaration::create([
            'user_id' => $employee->id,
            'employee_name' => 'Budi Santoso',
            'employee_nik' => '1234567890123456',
            'employee_nip' => '1980010100000001',
            'employee_position' => 'Analis GRC',
            'employee_work_unit' => 'Unit Tata Kelola',
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
            'approved_label' => 'APPROVED',
            'submitted_at' => now(),
            'pdf_path' => 'declarations/' . 1 . '.pdf',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.data.export', ['ids' => [$declaration->id]]));

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/zip');
    }

    public function test_admin_dapat_menonaktifkan_dan_mengaktifkan_akun_lain(): void
    {
        $admin = $this->makeAdmin();
        $target = $this->makeEmployee(['is_active' => true]);

        // Nonaktifkan.
        $this->actingAs($admin)
            ->put("/admin/users/{$target->id}/status", ['is_active' => false])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertFalse($target->fresh()->is_active);

        // Aktifkan kembali.
        $this->actingAs($admin)
            ->put("/admin/users/{$target->id}/status", ['is_active' => true])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_admin_tidak_dapat_menonaktifkan_akun_sendiri(): void
    {
        $admin = $this->makeAdmin(['is_active' => true]);

        $response = $this->actingAs($admin)
            ->put("/admin/users/{$admin->id}/status", ['is_active' => false]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('error');

        // Status admin tetap aktif (guard mencegah penonaktifan diri sendiri).
        $this->assertTrue($admin->fresh()->is_active);
    }

    /**
     * Req 7.1 / 7.2: perubahan status akun yang berhasil menulis tepat satu
     * baris audit `account_status_changed` dengan user_id = admin pelaku dan
     * target = akun yang terpengaruh (target_type=User, target_id=akun target).
     */
    public function test_perubahan_status_menulis_satu_baris_audit_dengan_pelaku_admin_dan_target_akun(): void
    {
        $admin = $this->makeAdmin();
        $target = $this->makeEmployee(['is_active' => true]);

        $this->actingAs($admin)
            ->put("/admin/users/{$target->id}/status", ['is_active' => false])
            ->assertRedirect(route('admin.users.index'));

        // Tepat satu baris audit perubahan status.
        $logs = AuditLog::where('event', AuditService::EVENT_ACCOUNT_STATUS_CHANGED)->get();
        $this->assertCount(1, $logs);

        $log = $logs->first();
        // Pelaku = admin yang melakukan perubahan (bukan akun target).
        $this->assertSame($admin->id, $log->user_id);
        // Target = akun yang terpengaruh.
        $this->assertSame(User::class, $log->target_type);
        $this->assertSame($target->id, (int) $log->target_id);
        $this->assertSame('account_status_changed', $log->event);
        $this->assertNotNull($log->created_at);
    }

    /**
     * Req 7.3: baris audit perubahan status hanya memuat kolom yang diharapkan
     * (pola attributes-only) dan TIDAK membocorkan password akun target.
     */
    public function test_baris_audit_perubahan_status_tidak_memuat_data_sensitif(): void
    {
        $admin = $this->makeAdmin();
        $target = $this->makeEmployee(['is_active' => true]);

        $this->actingAs($admin)
            ->put("/admin/users/{$target->id}/status", ['is_active' => false])
            ->assertRedirect(route('admin.users.index'));

        $log = AuditLog::where('event', AuditService::EVENT_ACCOUNT_STATUS_CHANGED)->firstOrFail();

        // Hanya kolom audit standar — tidak ada payload bebas.
        $this->assertEqualsCanonicalizing(
            ['id', 'user_id', 'event', 'target_type', 'target_id', 'created_at'],
            array_keys($log->getAttributes()),
        );

        // Hash password akun target tidak muncul di baris audit.
        $serialized = json_encode($log->getAttributes());
        $this->assertStringNotContainsString($target->fresh()->password, $serialized);
    }

    /**
     * Req 8.2: upaya menonaktifkan akun sendiri (ditolak guard) TIDAK menulis
     * baris audit perubahan status — tidak ada perubahan maka tidak ada jejak.
     */
    public function test_penonaktifan_diri_sendiri_yang_ditolak_tidak_menulis_audit(): void
    {
        $admin = $this->makeAdmin(['is_active' => true]);

        $this->actingAs($admin)
            ->put("/admin/users/{$admin->id}/status", ['is_active' => false])
            ->assertSessionHas('error');

        $this->assertSame(
            0,
            AuditLog::where('event', AuditService::EVENT_ACCOUNT_STATUS_CHANGED)->count(),
        );
    }

    /**
     * Req 8.2: admin hanya mengelola status akun, bukan isi deklarasi. Mengubah
     * status akun TIDAK mengubah baris deklarasi milik akun tersebut. Tidak ada
     * jalur admin untuk menyunting isi deklarasi; perubahan status terisolasi
     * dari konten deklarasi.
     */
    public function test_perubahan_status_tidak_mengubah_isi_deklarasi(): void
    {
        $admin = $this->makeAdmin();
        $target = $this->makeEmployee(['is_active' => true]);

        // Buat deklarasi + rincian milik akun target.
        $declaration = ConflictDeclaration::create([
            'user_id' => $target->id,
            'employee_name' => 'Budi Santoso',
            'employee_nik' => '1234567890123456',
            'employee_position' => 'Staf',
            'employee_work_unit' => 'Keuangan',
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
            'approved_label' => 'APPROVED',
            'submitted_at' => now(),
        ]);

        $detail = ConflictDeclarationDetail::create([
            'declaration_id' => $declaration->id,
            'category_key' => 'financial_interest',
            'related_party_name' => 'PT Contoh',
            'situation_explanation' => 'Memiliki saham signifikan.',
        ]);

        $declarationSnapshot = $declaration->fresh()->getAttributes();
        $detailSnapshot = $detail->fresh()->getAttributes();

        // Toggle status akun (nonaktif lalu aktif kembali).
        $this->actingAs($admin)
            ->put("/admin/users/{$target->id}/status", ['is_active' => false])
            ->assertRedirect(route('admin.users.index'));
        $this->actingAs($admin)
            ->put("/admin/users/{$target->id}/status", ['is_active' => true])
            ->assertRedirect(route('admin.users.index'));

        // Isi deklarasi dan rincian tidak berubah sama sekali.
        $this->assertEquals($declarationSnapshot, $declaration->fresh()->getAttributes());
        $this->assertEquals($detailSnapshot, $detail->fresh()->getAttributes());

        // Tidak ada event audit terkait deklarasi akibat perubahan status akun.
        $this->assertSame(
            0,
            AuditLog::where('event', AuditService::EVENT_DECLARATION_SUBMITTED)->count(),
        );
    }
}
