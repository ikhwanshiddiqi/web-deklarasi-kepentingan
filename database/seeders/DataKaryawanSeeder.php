<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DataKaryawanSeeder extends Seeder
{
    private const FILE = 'seeders/data/datakaryawan.xlsx';

    private const HEADERS = ['email', 'nik', 'nip', 'password', 'role', 'fullName', 'position', 'workUnit'];

    public function run(): void
    {
        $path = database_path(self::FILE);
        if (! is_file($path)) {
            $this->command?->error("File tidak ditemukan: {$path}");

            return;
        }

        $rows = $this->readRows($path);
        if ($rows === null) {
            return;
        }

        $errors = $this->validate($rows);
        if ($errors !== []) {
            $this->command?->error('Impor dibatalkan. Perbaiki baris berikut di Excel:');
            foreach (array_slice($errors, 0, 30) as $e) {
                $this->command?->line(' - ' . $e);
            }
            if (count($errors) > 30) {
                $this->command?->line(' ... dan ' . (count($errors) - 30) . ' kesalahan lain');
            }

            return;
        }

        // Hash bcrypt itu lambat (~100 ms). Password yang sama cukup di-hash sekali.
        $hashes = [];
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$hashes, &$created, &$updated) {
            foreach ($rows as $row) {
                $hashes[$row['password']] ??= Hash::make($row['password']);

                $user = User::updateOrCreate(
                    ['email' => $row['email']],
                    [
                        'nik' => $row['nik'],
                        'nip' => $row['nip'],
                        'password' => $hashes[$row['password']],
                        'role' => $row['role'],
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ],
                );

                $user->wasRecentlyCreated ? $created++ : $updated++;

                EmployeeProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'full_name' => $row['fullName'],
                        'position' => $row['position'],
                        'work_unit' => $row['workUnit'],
                    ],
                );
            }
        });

        $this->command?->info("Selesai: {$created} akun baru, {$updated} akun diperbarui.");
    }

    /** @return list<array<string,string>>|null */
    private function readRows(string $path): ?array
    {
        // toArray(..., formatData=true): sel berformat Text tetap string (NIK/NIP tidak jadi 2E+15)
        $data = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);

        $header = array_map(fn($h) => trim((string) $h), array_shift($data) ?? []);
        $missing = array_diff(self::HEADERS, $header);
        if ($missing !== []) {
            $this->command?->error('Header tidak lengkap/berbeda: ' . implode(', ', $missing));

            return null;
        }

        $index = array_flip($header);
        $rows = [];
        foreach ($data as $n => $cells) {
            $row = [];
            foreach (self::HEADERS as $h) {
                $row[$h] = trim((string) ($cells[$index[$h]] ?? ''));
            }
            if (implode('', $row) === '') {
                continue; // baris kosong
            }
            $row['_line'] = $n + 2; // nomor baris di Excel
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return list<string> */
    private function validate(array $rows): array
    {
        $errors = [];
        $seen = [];

        foreach ($rows as $row) {
            $line = $row['_line'];
            $email = mb_strtolower($row['email']);

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "baris {$line}: email tidak valid ({$row['email']})";
            } elseif (isset($seen[$email])) {
                $errors[] = "baris {$line}: email sama dengan baris {$seen[$email]} ({$row['email']})";
            }
            $seen[$email] ??= $line;

            if (! in_array($row['role'], ['admin', 'employee'], true)) {
                $errors[] = "baris {$line}: role harus admin atau employee (isi: '{$row['role']}')";
            }
            if ($row['password'] === '' || mb_strlen($row['password']) < 8) {
                $errors[] = "baris {$line}: password minimal 8 karakter";
            }
            foreach (['nik', 'nip'] as $f) {
                if ($row[$f] !== '' && ! ctype_digit($row[$f])) {
                    $errors[] = "baris {$line}: {$f} harus angka saja (isi: '{$row[$f]}') - format kolom Excel sebagai Text";
                }
            }
            if ($row['fullName'] === '') {
                $errors[] = "baris {$line}: fullName kosong";
            }
        }

        return $errors;
    }
}