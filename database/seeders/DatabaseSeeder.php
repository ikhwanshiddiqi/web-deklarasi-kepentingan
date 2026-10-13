<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder akun uji: satu admin dan satu karyawan.
 *
 * Kedua akun dibuat aktif + email terverifikasi + profil lengkap sehingga
 * langsung dapat login dan mengisi form deklarasi. Idempoten: dijalankan ulang
 * memperbarui akun yang sama (berdasarkan email), tidak menduplikasi.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUser(
            email: 'admin@sig.id',
            nik: '1000000000000001',
            password: 'password123',
            role: 'admin',
            fullName: 'Administrator Portal',
            position: 'Administrator GRC',
            workUnit: 'Unit Tata Kelola',
        );

        $this->seedUser(
            email: 'karyawan@sig.id',
            nik: '2000000000000002',
            password: 'password123',
            role: 'employee',
            fullName: 'Budi Santoso',
            position: 'Analis GRC',
            workUnit: 'Unit Kepatuhan',
        );
    }

    private function seedUser(
        string $email,
        string $nik,
        string $password,
        string $role,
        string $fullName,
        string $position,
        string $workUnit,
    ): void {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'nik' => $nik,
                'password' => Hash::make($password),
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        EmployeeProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $fullName,
                'position' => $position,
                'work_unit' => $workUnit,
            ],
        );
    }
}
