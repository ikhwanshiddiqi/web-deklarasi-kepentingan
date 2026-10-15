<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeclarationRequest;
use App\Models\ConflictDeclaration;
use App\Services\AuditService;
use App\Services\DeclarationService;
use App\Services\PdfService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeclarationController extends Controller
{
    public function __construct(
        private readonly DeclarationService $declarations,
        private readonly AuditService $audit,
        private readonly PdfService $pdf,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user()->loadMissing('profile');
        $existing = $user->latestDeclaration();

        if ($existing !== null) {
            return redirect()
                ->route('declarations.edit', $existing)
                ->with('warning', 'Anda sudah pernah mengisi deklarasi. Silakan edit jawaban sebelumnya.');
        }

        $profile = $user->profile;

        $employee = [
            'full_name' => $profile?->full_name,
            'nik' => $user->nik,
            'nip' => $user->nip,
            'position' => $profile?->position,
            'work_unit' => $profile?->work_unit,
        ];

        $categories = config('conflict_categories');

        return view('declarations.create', [
            'user' => $user,
            'employee' => $employee,
            'categories' => $categories,
            'declaration' => null,
        ]);
    }

    public function store(StoreDeclarationRequest $request): RedirectResponse
    {
        $user = $request->user()->loadMissing('profile');

        $existing = $user->latestDeclaration();
        if ($existing !== null) {
            return redirect()
                ->route('declarations.edit', $existing)
                ->with('warning', 'Anda sudah pernah mengisi deklarasi. Jawaban sebelumnya dapat diedit.');
        }

        $data = $request->normalized();

        $declaration = $this->declarations->store($user, $data);

        $this->audit->log(
            AuditService::EVENT_DECLARATION_SUBMITTED,
            $declaration,
            $user->id,
        );

        return redirect()
            ->route('declarations.show', $declaration)
            ->with('status', 'Deklarasi berhasil disimpan.');
    }

    public function edit(Request $request, ConflictDeclaration $declaration): View
    {
        $this->authorize('update', $declaration);

        $declaration->loadMissing(['categories', 'details']);

        $owner = $declaration->user()->with('profile')->firstOrFail();
        $profile = $owner->profile;

        $employee = [
            'full_name' => $profile?->full_name,
            'nik' => $owner->nik,
            'nip' => $owner->nip,
            'position' => $profile?->position,
            'work_unit' => $profile?->work_unit,
        ];

        return view('declarations.create', [
            'user' => $owner,
            'employee' => $employee,
            'categories' => config('conflict_categories', []),
            'declaration' => $declaration,
        ]);
    }

    public function update(StoreDeclarationRequest $request, ConflictDeclaration $declaration): RedirectResponse
    {
        $this->authorize('update', $declaration);

        $this->declarations->update($declaration, $request->normalized());

        return redirect()
            ->route('declarations.show', $declaration)
            ->with('status', 'Deklarasi berhasil diperbarui.');
    }

    public function show(Request $request, ConflictDeclaration $declaration): View
    {
        $this->authorize('view', $declaration);

        // Muat relasi untuk ringkasan Bagian B/C pada halaman hasil.
        $declaration->loadMissing(['categories', 'details']);

        return view('declarations.show', [
            'declaration' => $declaration,
            'categories' => config('conflict_categories', []),
        ]);
    }

    public function preview(Request $request, ConflictDeclaration $declaration)
    {
        $this->authorize('view', $declaration);

        $version = $declaration->updated_at?->timestamp ?? $declaration->id;
        $filename = 'deklarasi-' . $declaration->id . '-' . $version . '.pdf';
        $contents = $this->pdf->generateCached($declaration, 900);

        $this->audit->log(
            AuditService::EVENT_PDF_GENERATED,
            $declaration,
            $request->user()->id,
        );

        return response($contents, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function pdf(Request $request, ConflictDeclaration $declaration): StreamedResponse
    {
        $this->authorize('download', $declaration);

        $version = $declaration->updated_at?->timestamp ?? $declaration->id;
        $filename = 'deklarasi-' . $declaration->id . '-' . $version . '.pdf';
        $contents = $this->pdf->generateCached($declaration, 900);

        $this->audit->log(
            AuditService::EVENT_PDF_GENERATED,
            $declaration,
            $request->user()->id,
        );

        $this->audit->log(
            AuditService::EVENT_PDF_DOWNLOADED,
            $declaration,
            $request->user()->id,
        );

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
