<?php

namespace App\Policies;

use App\Models\ConflictDeclaration;
use App\Models\User;

/**
 * Policy otorisasi deklarasi benturan kepentingan (Requirement 6).
 *
 * Aturan tunggal untuk melihat halaman hasil maupun mengunduh PDF: pengguna
 * harus pemilik deklarasi (declaration.user_id === user.id) ATAU memiliki role
 * admin (Req 5.5, 6.1, 6.4; design.md bagian 8).
 *
 * Policy ini dipakai lewat `authorize()` di {@see \App\Http\Controllers\DeclarationController}
 * sehingga kontrol akses dilakukan di server — bukan sekadar menyembunyikan
 * tombol di UI (Req 6.1). Dengan route-model binding, upaya mengganti ID URL
 * ke deklarasi milik akun lain (IDOR) ditolak 403 (Req 6.2).
 *
 * Requirements: 5.5, 6.1, 6.2, 6.4
 */
class DeclarationPolicy
{
    /**
     * Boleh melihat halaman hasil deklarasi (Req 6.1, 6.2).
     */
    public function view(User $user, ConflictDeclaration $declaration): bool
    {
        return $this->ownsOrAdmin($user, $declaration);
    }

    /**
     * Boleh mengunduh PDF deklarasi (Req 5.5, 6.4).
     */
    public function download(User $user, ConflictDeclaration $declaration): bool
    {
        return $this->ownsOrAdmin($user, $declaration);
    }

    /**
     * Boleh memperbarui jawaban deklarasi milik sendiri.
     */
    public function update(User $user, ConflictDeclaration $declaration): bool
    {
        return $this->ownsOrAdmin($user, $declaration);
    }

    /**
     * Aturan bersama: pemilik deklarasi atau admin.
     */
    private function ownsOrAdmin(User $user, ConflictDeclaration $declaration): bool
    {
        return $declaration->user_id === $user->id || $user->isAdmin();
    }
}
