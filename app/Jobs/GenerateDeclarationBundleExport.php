<?php

namespace App\Jobs;

use App\Models\ConflictDeclaration;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use ZipArchive;

class GenerateDeclarationBundleExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?string $archivePath = null;

    public function __construct(
        public array $declarationIds,
        public ?int $requestedBy = null,
    ) {}

    public function handle(PdfService $pdfService): ?string
    {
        if (empty($this->declarationIds)) {
            return null;
        }

        $declarations = ConflictDeclaration::query()
            ->with(['categories', 'details'])
            ->whereIn('id', $this->declarationIds)
            ->orderBy('submitted_at', 'desc')
            ->get();

        if ($declarations->isEmpty()) {
            return null;
        }

        $tempDir = storage_path('app/tmp/bundles/' . Str::uuid()->toString());
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0775, true) && ! is_dir($tempDir)) {
            return null;
        }

        foreach ($declarations as $declaration) {
            $filename = 'deklarasi-' . $declaration->id . '.pdf';
            $contents = $pdfService->generateCached($declaration, 3600);
            file_put_contents($tempDir . '/' . $filename, $contents);
        }

        $archivePath = 'exports/bundle-' . Str::uuid()->toString() . '.zip';
        $fullArchivePath = storage_path('app/' . $archivePath);
        $archiveDir = dirname($fullArchivePath);

        if (! is_dir($archiveDir) && ! mkdir($archiveDir, 0775, true) && ! is_dir($archiveDir)) {
            return null;
        }

        $zip = new ZipArchive();
        $opened = $zip->open($fullArchivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            return null;
        }

        foreach (glob($tempDir . '/*.pdf') as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();

        foreach (glob($tempDir . '/*.pdf') as $file) {
            @unlink($file);
        }

        @rmdir($tempDir);

        $this->archivePath = $archivePath;

        return $archivePath;
    }
}
