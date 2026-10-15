<?php

namespace App\Services;

use App\Models\ConflictDeclaration;
use App\Models\User;
use App\Services\RichTextService as RichText;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export Excel seluruh pengguna (kecuali admin), termasuk yang belum mengisi deklarasi.
 *
 * Template: header di baris 4 (A-L), data mulai baris 5. Satu baris per rincian; data karyawan
 * diulang di tiap baris (tanpa merge) agar bisa difilter. Pengguna yang belum mengisi mendapat
 * satu baris dengan Status Pengisian "Belum Mengisi".
 */
class DeclarationExcelExport
{
    private const FIRST_ROW = 5;

    public const TEMPLATE = 'templates/template_excel.xlsx'; // relatif ke storage/app

    /** @return string path file .xlsx sementara */
    public function build(): string
    {
        $book = IOFactory::load(storage_path('app/' . self::TEMPLATE));
        $sheet = $book->getActiveSheet();
        $labels = collect(config('conflict_categories', []))->map(fn($c) => $c['label'] ?? null)->all();

        $row = self::FIRST_ROW;
        $no = 0;

        // Definisi "admin" sama dengan DeclarationStats (kolom role).
        User::query()
            ->where(fn($q) => $q->whereNull('role')->orWhere('role', '!=', 'admin'))
            ->with('profile')
            ->chunkById(200, function ($users) use (&$row, &$no, $sheet, $labels) {
                // Deklarasi terbaru tiap pengguna (urut naik, yang terakhir menimpa)
                $declarations = ConflictDeclaration::query()
                    ->whereIn('user_id', $users->pluck('id'))
                    ->with('details')
                    ->orderBy('submitted_at')
                    ->orderBy('id')
                    ->get()
                    ->keyBy('user_id');

                foreach ($users as $user) {
                    $no++;
                    $declaration = $declarations->get($user->id);
                    $filled = $declaration !== null;
                    $hasConflict = $filled
                        && $declaration->declaration_status === ConflictDeclaration::STATUS_HAS_CONFLICT;
                    $details = $hasConflict ? $declaration->details->values() : collect();
                    $count = max(1, $details->count());
                    $start = $row;
                    $end = $row + $count - 1;

                    // Sudah mengisi: pakai data yang tersimpan di deklarasi; belum: data profil akun
                    $profile = $user->profile;
                    $employee = [
                        'A' => $no,
                        'B' => $filled ? $declaration->employee_name : ($profile?->full_name ?? $user->email),
                        'C' => (string) ($filled ? ($declaration->employee_nik ?? $user->nik) : $user->nik),
                        'D' => (string) ($user->nip ?? ''),
                        'E' => $filled ? $declaration->employee_work_unit : $profile?->work_unit,
                        'F' => $filled ? $declaration->employee_position : $profile?->position,
                        'G' => $filled ? 'Sudah Mengisi' : 'Belum Mengisi',
                        'H' => $filled
                            ? $declaration->submitted_at?->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i')
                            : '-',
                        'I' => ! $filled
                            ? '-'
                            : ($hasConflict ? 'Memiliki benturan kepentingan' : 'Tidak memiliki benturan kepentingan'),
                    ];

                    for ($r = $start; $r <= $end; $r++) {
                        foreach ($employee as $col => $value) {
                            if ($col === 'C' || $col === 'D') {
                                $sheet->setCellValueExplicit("{$col}{$r}", $value, DataType::TYPE_STRING);
                            } else {
                                $sheet->setCellValue("{$col}{$r}", $value);
                            }
                        }
                    }

                    if ($details->isEmpty()) {
                        foreach (['J', 'K', 'L'] as $col) {
                            $sheet->setCellValue("{$col}{$start}", '-');
                        }
                    }

                    foreach ($details as $i => $detail) {
                        $r = $start + $i;
                        $sheet->setCellValue("J{$r}", $labels[$detail->category_key] ?? $detail->category_key);
                        $sheet->setCellValue("K{$r}", $detail->related_party_name);
                        $sheet->setCellValue("L{$r}", implode("\n", RichText::toLines($detail->situation_explanation)));
                    }

                    $row = $end + 1;
                }
            });

        $last = $row - 1;
        if ($last >= self::FIRST_ROW) {
            $first = self::FIRST_ROW;
            $range = "A{$first}:L{$last}";
            $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle($range)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            // A, C, D, G, H rata tengah; sisanya rata kiri
            foreach (['A', 'C', 'D', 'G', 'H'] as $col) {
                $sheet->getStyle("{$col}{$first}:{$col}{$last}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $sheet->setAutoFilter("A4:L{$last}");
            $sheet->freezePane('A5');
        }

        $path = tempnam(sys_get_temp_dir(), 'decl_xlsx_');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
