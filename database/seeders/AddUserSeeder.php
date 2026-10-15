<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AddUserSeeder extends Seeder
{
    public function run(): void
    {

        $this->seedUser(
            email: 'karyawan1@sig.id',
            nik: '2000000000000001',
            nip: '1980030300000001',
            password: 'password123',
            role: 'employee',
            fullName: 'Putra Jaya',
            position: 'Karyawan',
            workUnit: 'Unit Pengembangan',
        );
        $this->seedUser(
            email: 'karyawan2@sig.id',
            nik: '2000000000000003',
            nip: '1980040400000002',
            password: 'password123',
            role: 'employee',
            fullName: 'Ani Permata',
            position: 'Anggota Tim',
            workUnit: 'Unit Perencanaan',
        );
    }

    private function seedUser(
        string $email,
        string $nik,
        string $nip,
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
                'nip' => $nip,
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
