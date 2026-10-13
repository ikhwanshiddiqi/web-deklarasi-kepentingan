<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test guard profil lengkap untuk form deklarasi (Req 3.4).
 *
 * Mencakup:
 * - Middleware EnsureProfileComplete: profil belum lengkap dialihkan ke
 *   Setting Akun dengan pesan peringatan; profil lengkap dapat mengakses.
 * - Navigasi: menu "Isi Form" dinonaktifkan (marker stabil) saat profil belum
 *   lengkap dan menjadi tautan saat lengkap.
 *
 * Requirements: 3.4
 */
class ProfileCompleteMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'budi@sig.id',
            'nik' => '1234567890123456',
        ], $overrides));
    }

    private function completeProfileFor(User $user): void
    {
        EmployeeProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Budi Santoso',
            'position' => 'Analis GRC',
            'work_unit' => 'Unit Tata Kelola',
        ]);
    }

    public function test_profil_belum_lengkap_dialihkan_dari_declarations_create(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/declarations/create');

        $response->assertRedirect('/account/settings');
        $response->assertSessionHas(
            'warning',
            'Lengkapi profil Anda terlebih dahulu sebelum mengisi form deklarasi.',
        );
    }

    public function test_profil_lengkap_dapat_mengakses_declarations_create(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $response = $this->actingAs($user)->get('/declarations/create');

        $response->assertOk();
    }

    public function test_tamu_tidak_dapat_mengakses_declarations_create(): void
    {
        $this->get('/declarations/create')->assertRedirect('/login');
    }

    public function test_nav_isi_form_dinonaktifkan_saat_profil_belum_lengkap(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/account/settings');

        $response->assertOk();
        // Marker stabil disabled (tidak bergantung pada styling).
        $response->assertSee('data-isi-form-disabled="true"', false);
        $response->assertSee(
            'Lengkapi profil Anda terlebih dahulu untuk mengisi form deklarasi.',
        );
    }

    public function test_nav_isi_form_menjadi_tautan_saat_profil_lengkap(): void
    {
        $user = $this->makeUser();
        $this->completeProfileFor($user);

        $response = $this->actingAs($user)->get('/account/settings');

        $response->assertOk();
        // Tautan aktif ke form deklarasi; marker disabled tidak muncul.
        $response->assertSee('data-isi-form="enabled"', false);
        $response->assertSee('href="'.url('/declarations/create').'"', false);
        $response->assertDontSee('data-isi-form-disabled="true"', false);
    }
}
