<?php

namespace App\Services;

use App\Models\ConflictDeclaration;
use Illuminate\Support\Facades\Cache;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use App\Services\RichTextService as RichText;

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
     * Render PDF (bytes) tanpa menyimpan ke disk.
     *
     * Baris tabel C yang panjang dipecah menjadi beberapa potongan agar bisa pindah halaman.
     * Supaya akhir tiap halaman tetap bergaris penutup, PDF dirender dua kali bila perlu:
     * pass 1 mengukur potongan mana yang jatuh di akhir halaman, pass 2 menggambar garisnya.
     */
    public function generate(ConflictDeclaration $declaration): string
    {
        $declaration->loadMissing(['categories', 'details']);

        $data = ['pageEnds' => [], 'measure' => false, 'fallbackBorders' => false];

        try {
            $data['chunkData'] = $this->buildChunks($declaration);
        } catch (\Throwable) {
            // tanpa data ukur, template memakai pemecahan sederhana
        }

        if ($this->hasSplitRows($declaration, $data['chunkData'] ?? null)) {
            $ends = $this->detectPageEnds($declaration, ['chunkData' => $data['chunkData'] ?? null]);
            if ($ends === null) {
                // Pengukuran tidak tersedia: beri garis di semua potongan (aman, hanya kurang rapi).
                $data['fallbackBorders'] = true;
            } else {
                $data['pageEnds'] = $ends;
            }
        }

        $pdf = $this->render($declaration, $data);

        // Pengaman: PDF yang jelas rusak tidak boleh dikirim / di-cache.
        if ($pdf->page > 20) {
            throw new \RuntimeException("PDF deklarasi {$declaration->id} berlebihan: {$pdf->page} halaman");
        }

        return $pdf->Output('', Destination::STRING_RETURN);
    }

    private function newMpdf(): Mpdf
    {
        $tempDirectory = storage_path('framework/cache/mpdf');
        if (! is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0777, true);
        }

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'freesans',
            'default_font_size' => 11,
            'margin_top' => 25,
            'margin_bottom' => 25,
            'margin_left' => 25,
            'margin_right' => 15,
            'margin_footer' => 12.5,
            'tempDir' => $tempDirectory,
        ]);
    }

    /** Lebar isi sel "Penjelasan" (mm): 39% dari 170 mm area cetak, dikurangi padding & margin aman. */
    private const EXPLAIN_WIDTH_MM = 61.0;

    /** Jumlah baris tampil per potongan (makin kecil makin halus mengisi halaman). */
    private const LINES_PER_CHUNK = 3;

    /**
     * Susun penjelasan jadi baris-baris yang sudah dipatahkan sesuai lebar kolom (pakai metrik font mPDF),
     * lalu kelompokkan per LINES_PER_CHUNK baris. Dengan begitu tiap potongan berisi baris penuh dan tidak
     * ada sisa kata menggantung di ujung potongan. Item daftar diberi indentasi gantung.
     *
     * @return array<int, list<string>> indeks rincian => daftar potongan (baris dipisah "\n")
     */
    private function buildChunks(ConflictDeclaration $declaration): array
    {
        $m = $this->newMpdf();
        $m->AddPage();
        $m->SetFont('freesans', '', 11);

        $nbsp = "\u{00A0}";
        $width = fn(string $t): float => (float) $m->GetStringWidth($t);

        $result = [];
        foreach ($declaration->details->values() as $i => $detail) {
            $visual = [];
            foreach (RichText::toLines($detail->situation_explanation ?? '') as $para) {
                $indent = '';
                if (preg_match('/^(\d+\.|•)\s/u', $para, $mm)) {
                    $indent = str_repeat($nbsp, mb_strlen($mm[1]) + 2);
                    $para = $mm[1] . $nbsp . mb_substr($para, mb_strlen($mm[1]) + 1);
                }

                $line = '';
                foreach (preg_split('/\s+/u', trim($para), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                    $try = $line === '' ? $word : $line . ' ' . $word;
                    if ($line !== '' && $width($try) > self::EXPLAIN_WIDTH_MM) {
                        $visual[] = $line;
                        $line = $indent . $word;
                    } else {
                        $line = $try;
                    }
                }
                $visual[] = $line;
            }

            $result[$i] = array_map(
                fn(array $g) => implode("\n", $g),
                array_chunk($visual ?: [''], self::LINES_PER_CHUNK)
            );
        }

        return $result;
    }

    private function render(ConflictDeclaration $declaration, array $data): Mpdf
    {
        $html = view('pdf.declaration', [
            'declaration' => $declaration,
            'categories' => config('conflict_categories', []),
        ] + $data)->render();

        $pdf = $this->newMpdf();

        $pdf->WriteHTML($html);

        return $pdf;
    }

    /** Ada penjelasan yang terpecah jadi >1 potongan baris? */
    private function hasSplitRows(ConflictDeclaration $declaration, ?array $chunkData): bool
    {
        if ($chunkData !== null) {
            foreach ($chunkData as $chunks) {
                if (count($chunks) > 1) {
                    return true;
                }
            }

            return false;
        }

        foreach ($declaration->details as $detail) {
            $lines = RichText::toLines($detail->situation_explanation ?? '');
            if (count($lines) > 1 || (isset($lines[0]) && mb_strlen($lines[0]) > 140)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pass 1: render dengan <bookmark> di tiap potongan, baca halaman masing-masing dari
     * $pdf->BMoutlines. Mengembalikan id potongan (c1, c2, ...) yang menjadi baris terakhir
     * di halamannya, atau null kalau data halaman tidak bisa dibaca.
     *
     * @return list<string>|null
     */
    private function detectPageEnds(ConflictDeclaration $declaration, array $extra = []): ?array
    {
        try {
            $pdf = $this->render($declaration, ['pageEnds' => [], 'measure' => true, 'fallbackBorders' => false] + $extra);
        } catch (\Throwable) {
            return null;
        }

        $pages = [];
        foreach ((array) ($pdf->BMoutlines ?? []) as $outline) {
            $id = $outline['t'] ?? null;
            $page = $outline['p'] ?? null;
            if (is_string($id) && preg_match('/^c(\d+)$/', $id, $m) && is_numeric($page)) {
                $pages[(int) $m[1]] = (int) $page;
            }
        }

        if ($pages === []) {
            return null;
        }

        ksort($pages);
        $numbers = array_keys($pages);
        $ends = [];
        foreach ($numbers as $i => $n) {
            $next = $numbers[$i + 1] ?? null;
            if ($next !== null && $pages[$next] > $pages[$n]) {
                $ends[] = 'c' . $n;
            }
        }

        return $ends;
    }

    /**
     * Generate PDF on demand dengan cache singkat untuk mencegah rendering ulang
     * saat user membuka halaman yang sama berulang kali dalam rentang waktu dekat.
     */
    public function generateCached(ConflictDeclaration $declaration, int $ttlInSeconds = 900): string
    {
        $templatePath = resource_path('views/pdf/declaration.blade.php');
        $templateVersion = is_file($templatePath) ? (string) filemtime($templatePath) : '0';
        $contentFingerprint = $this->contentFingerprint($declaration);

        $cacheKey = sprintf(
            'declaration-pdf:%d:%s:%s',
            $declaration->id,
            $contentFingerprint,
            $templateVersion,
        );

        return Cache::remember($cacheKey, $ttlInSeconds, function () use ($declaration) {
            return $this->generate($declaration);
        });
    }

    private function contentFingerprint(ConflictDeclaration $declaration): string
    {
        $declaration->loadMissing(['categories', 'details']);

        $payload = [
            'id' => $declaration->id,
            'status' => $declaration->declaration_status,
            'employee_name' => $declaration->employee_name,
            'employee_nik' => $declaration->employee_nik,
            'employee_position' => $declaration->employee_position,
            'employee_work_unit' => $declaration->employee_work_unit,
            'submitted_at' => $declaration->submitted_at?->toDateTimeString(),
            'updated_at' => $declaration->updated_at?->toDateTimeString(),
            'categories' => $declaration->categories
                ->map(fn($category) => is_array($category)
                    ? ($category['category_key'] ?? null)
                    : ($category->category_key ?? null))
                ->filter()
                ->values()
                ->all(),
            'details' => $declaration->details
                ->map(fn($detail) => [
                    'category_key' => is_array($detail) ? ($detail['category_key'] ?? null) : ($detail->category_key ?? null),
                    'related_party_name' => is_array($detail) ? ($detail['related_party_name'] ?? null) : ($detail->related_party_name ?? null),
                    'situation_explanation' => is_array($detail) ? ($detail['situation_explanation'] ?? null) : ($detail->situation_explanation ?? null),
                ])
                ->values()
                ->all(),
        ];

        return md5(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
