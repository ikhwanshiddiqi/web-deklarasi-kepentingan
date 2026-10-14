<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDeclarationBundleExport;
use App\Models\ConflictDeclaration;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Tampilkan daftar akun (paginasi).
     *
     * Menampilkan email, NIK, role, status aktif, dan tanggal dibuat.
     */
    public function index(Request $request): View
    {
        $perPage = (int) $request->query('per_page', 10);
        $allowedPerPage = [10, 25, 50, 100];

        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $users = User::query()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends(['per_page' => $perPage]);

        return view('admin.users.index', [
            'users' => $users,
            'perPage' => $perPage,
        ]);
    }

    public function declarationsIndex(Request $request): View
    {
        $perPage = (int) $request->query('per_page', 10);
        $allowedPerPage = [10, 25, 50, 100];

        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $declarations = ConflictDeclaration::query()
            ->with('user')
            ->latest('submitted_at')
            ->paginate($perPage)
            ->appends(['per_page' => $perPage]);

        return view('admin.data.index', [
            'declarations' => $declarations,
            'perPage' => $perPage,
        ]);
    }

    public function exportBundle(Request $request)
    {
        $selectedIds = $request->query('ids');

        $ids = is_array($selectedIds)
            ? array_filter(array_map('intval', $selectedIds))
            : ConflictDeclaration::query()->pluck('id')->map(fn($id) => (int) $id)->all();

        if (empty($ids)) {
            return redirect()->route('admin.data.index')
                ->with('error', 'Tidak ada deklarasi yang bisa diekspor.');
        }

        $job = new GenerateDeclarationBundleExport($ids, Auth::id());
        $archiveFile = $job->handle(app()->make(\App\Services\PdfService::class));

        if (! $archiveFile || ! Storage::disk('local')->exists($archiveFile)) {
            return redirect()->route('admin.data.index')
                ->with('error', 'Ekor ekspor bundle gagal dibuat. Silakan coba lagi.');
        }

        return response()->download(
            Storage::disk('local')->path($archiveFile),
            'bundle-deklarasi-' . now()->format('Ymd_His') . '.zip',
            ['Content-Type' => 'application/zip'],
        );
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $validated['is_active'];

        // Guard: admin tidak boleh menonaktifkan akunnya sendiri.
        if ($user->id === Auth::id() && $isActive === false) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $user->is_active = $isActive;
        $user->save();

        $this->auditService->log(
            AuditService::EVENT_ACCOUNT_STATUS_CHANGED,
            $user,
        );

        return redirect()
            ->route('admin.users.index')
            ->with('status', $isActive
                ? 'Akun berhasil diaktifkan.'
                : 'Akun berhasil dinonaktifkan.');
    }
}
