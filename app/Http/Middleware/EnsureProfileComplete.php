<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pastikan profil karyawan sudah lengkap sebelum mengakses form deklarasi.
 *
 * Jika user terautentikasi belum melengkapi profil (Nama Lengkap, NIK,
 * Jabatan/Posisi, Unit Kerja), permintaan dialihkan ke halaman Setting Akun
 * dengan pesan peringatan. Ini melengkapi penonaktifan menu "Isi Form" di
 * navigasi (Req 3.4): penjaga sisi server mencegah akses langsung via URL,
 * tidak hanya menyembunyikan tombol di UI.
 *
 * Requirements: 3.4
 */
class EnsureProfileComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->hasCompleteProfile()) {
            return redirect()
                ->route('account.settings')
                ->with('warning', 'Lengkapi profil Anda terlebih dahulu sebelum mengisi form deklarasi.');
        }

        return $next($request);
    }
}
