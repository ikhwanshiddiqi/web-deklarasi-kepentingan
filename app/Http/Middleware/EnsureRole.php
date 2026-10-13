<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pastikan user terautentikasi memiliki role tertentu sebelum mengakses route.
 *
 * Dipakai untuk membatasi area admin (Req 8.3): akses hanya untuk akun dengan
 * role yang ditentukan, mis. `->middleware('role:admin')`. Penjaga ini bekerja
 * di sisi server (bukan sekadar menyembunyikan menu). Jika tidak ada user
 * terautentikasi atau role tidak cocok, permintaan ditolak dengan 403.
 *
 * Requirements: 8.3
 */
class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $role  Role yang diizinkan (mis. "admin").
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || $user->role !== $role) {
            abort(403);
        }

        return $next($request);
    }
}
