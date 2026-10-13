<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi pembaruan profil karyawan — Setting Akun (Requirement 3).
 *
 * Field yang divalidasi: Nama Lengkap, Jabatan/Posisi, dan Unit Kerja —
 * ketiganya wajib (Req 3.2). Email dan NIK TIDAK termasuk dalam aturan dan
 * tidak boleh diubah lewat endpoint ini (Req 3.1); nilai keduanya diambil
 * backend dari database (Req 3.5).
 *
 * Requirements: 3.1, 3.2
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * Otorisasi ditangani oleh middleware `auth` pada rute.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi.
     *
     * Sengaja TIDAK memuat `email` maupun `nik` sehingga bila browser mengirim
     * nilai tersebut, mereka tidak lolos `validated()` dan diabaikan (Req 3.1).
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'work_unit' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Pesan kesalahan dalam Bahasa Indonesia (Req 10.4 — pesan dekat field).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama Lengkap wajib diisi.',
            'full_name.string' => 'Nama Lengkap tidak valid.',
            'full_name.max' => 'Nama Lengkap maksimal 255 karakter.',
            'position.required' => 'Jabatan/Posisi wajib diisi.',
            'position.string' => 'Jabatan/Posisi tidak valid.',
            'position.max' => 'Jabatan/Posisi maksimal 255 karakter.',
            'work_unit.required' => 'Unit Kerja wajib diisi.',
            'work_unit.string' => 'Unit Kerja tidak valid.',
            'work_unit.max' => 'Unit Kerja maksimal 255 karakter.',
        ];
    }

    /**
     * Nama atribut untuk pesan validasi.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'Nama Lengkap',
            'position' => 'Jabatan/Posisi',
            'work_unit' => 'Unit Kerja',
        ];
    }
}
