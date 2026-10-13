<?php

namespace App\Services;

use App\Models\ConflictDeclaration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;

/**
 * Layanan pembuatan PDF deklarasi benturan kepentingan.
 *
 * Perubahan desain terbaru: PDF tidak disimpan di local disk. Dokumen hanya
 * dibuat secara on-demand saat user membuka preview atau mengunduh PDF.
 * Artinya, tidak ada file persist yang dibuat di storage; output berupa byte
 * PDF yang langsung dikembalikan ke browser.
 */
class PdfService
{
    /**
     * Tetap ada untuk kompatibilitas kode lama, namun tidak dipakai lagi untuk
     * penyimpanan file karena PDF dibuat on-demand saja.
     */
    public const DISK = 'local';

    public const DIRECTORY = 'declarations';

    /**
     * Render PDF sekaligus menampilkannya ke browser tanpa menyimpan ke disk.
     *
     * @return string bytes PDF.
     */
    public function generate(ConflictDeclaration $declaration): string
    {
        $declaration->loadMissing(['categories', 'details']);

        $pdf = Pdf::loadView('pdf.declaration', [
            'declaration' => $declaration,
            'categories' => config('conflict_categories', []),
        ])->setPaper('a4');

        return $pdf->output();
    }

    /**
     * Generate PDF on demand dengan cache singkat untuk mencegah rendering ulang
     * saat user membuka halaman yang sama berulang kali dalam rentang waktu dekat.
     */
    public function generateCached(ConflictDeclaration $declaration, int $ttlInSeconds = 900): string
    {
        $cacheKey = sprintf(
            'declaration-pdf:%d:%s',
            $declaration->id,
            $declaration->updated_at?->timestamp ?? $declaration->created_at?->timestamp ?? 0,
        );

        return Cache::remember($cacheKey, $ttlInSeconds, function () use ($declaration) {
            return $this->generate($declaration);
        });
    }
}
