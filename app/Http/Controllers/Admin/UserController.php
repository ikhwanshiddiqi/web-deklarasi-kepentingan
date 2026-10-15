<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDeclarationBundleExport;
use App\Models\ConflictDeclaration;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DeclarationExcelExport;
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

        $search = trim((string) $request->query('q', ''));

        $status = $request->query('status');
        $status = in_array($status, [ConflictDeclaration::STATUS_HAS_CONFLICT, 'none'], true)
            ? $status
            : null;

        $declarations = ConflictDeclaration::query()
            ->with('user')
            ->when($search !== '', function ($query) use ($search) {
                $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search, 'UTF-8'));
                $like = '%' . $term . '%';

                $query->where(function ($q) use ($like) {
                    $q->whereRaw("LOWER(employee_name) LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("LOWER(employee_nik) LIKE ? ESCAPE '!'", [$like])
                        ->orWhereHas('user', fn($u) => $u->whereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$like]));
                });
            })
            ->when($status, fn($query) => $query->where('declaration_status', $status))
            ->latest('submitted_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.data.index', [
            'declarations' => $declarations,
            'perPage' => $perPage,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function exportBundle(Request $request)
    {
        $ids = collect((array) $request->query('ids', []))
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(100)
            ->values()
            ->all();

        if ($ids === []) {
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

    private function declarationsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $status = in_array($status, [ConflictDeclaration::STATUS_HAS_CONFLICT, 'none'], true) ? $status : null;

        return ConflictDeclaration::query()
            ->when($search !== '', function ($query) use ($search) {
                $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search, 'UTF-8'));
                $like = '%' . $term . '%';

                $query->where(function ($q) use ($like) {
                    $q->whereRaw("LOWER(employee_name) LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("LOWER(employee_nik) LIKE ? ESCAPE '!'", [$like])
                        ->orWhereHas('user', fn($u) => $u->whereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$like]));
                });
            })
            ->when($status, fn($query) => $query->where('declaration_status', $status))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');
    }

    public function exportExcel(DeclarationExcelExport $export)
    {
        $path = $export->build();

        return response()
            ->download($path, 'data-deklarasi-' . now('Asia/Jakarta')->format('Ymd_His') . '.xlsx')
            ->deleteFileAfterSend(true);
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
