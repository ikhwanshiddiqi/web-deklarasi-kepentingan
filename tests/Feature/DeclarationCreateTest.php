<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test halaman Isi Form deklarasi (Task 6.1).
 *
 * Mencakup konten yang dirender server:
 * - Bagian A menampilkan data profil (nama, NIK, jabatan, unit kerja) yang
 *   diambil backend dari user login (Req 4.1).
 * - Bagian B menampilkan 5 label kategori dari config (Req 4.2, 4.3).
 * - Pernyataan tetap tercetak (Req 4.13 / design).
 * - Guard profil lengkap tetap bekerja setelah mengganti placeholder (Req 3.4).
 *
 * Catatan: interaksi Alpine.js (kondisional/tabel dinamis) tidak diuji di
 * PHPUnit; hanya konten yang dirender server yang diverifikasi.
 *
 * Requirements: 4.1, 4.2, 4.3, 10.3, 10.4
 */
class DeclarationCreateTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'budi@sig.id',
            'nik' => '1234567890123456',
        ], $overrides));
    }

    private function completeProfileFor(User $user): EmployeeProfile
    {
        return EmployeeProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Budi Santoso',
            'position' => 'Analis GRC',
            'work_unit' => 'Unit Tata Kelola',
        ]);
    }

    public function test_user_profil_lengkap_dapat_membuka_form_dan_melihat_data_profil(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $response = $this->actingAs($user)->get('/declarations/create');

        $response->assertOk();

        // Bagian A — data karyawan dari profil/akun (Req 4.1).
        $response->assertSee('Budi Santoso');
        $response->assertSee('1234567890123456');
        $response->assertSee('Analis GRC');
        $response->assertSee('Unit Tata Kelola');

        // Heading section A/B.
        $response->assertSee('A. Data Karyawan');
        $response->assertSee('B. Deklarasi Benturan Kepentingan');
    }

    public function test_form_menampilkan_lima_label_kategori_dari_config(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $response = $this->actingAs($user)->get('/declarations/create');

        $response->assertOk();

        foreach (config('conflict_categories') as $category) {
            $response->assertSee($category['label']);
        }
    }

    public function test_form_menampilkan_pernyataan_tetap_dan_tombol_simpan(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $response = $this->actingAs($user)->get('/declarations/create');

        $response->assertOk();
        $response->assertSee(
            'Saya menyatakan bahwa seluruh informasi ini adalah benar dan lengkap',
        );
        $response->assertSee('APPROVED');
        $response->assertSee('Simpan dan Buat PDF');
    }

    public function test_user_profil_belum_lengkap_dialihkan_ke_setting_akun(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/declarations/create');

        $response->assertRedirect('/account/settings');
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get('/declarations/create')->assertRedirect('/login');
    }
}
