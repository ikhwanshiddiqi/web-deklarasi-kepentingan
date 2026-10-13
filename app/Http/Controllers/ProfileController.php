<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function edit(): View
    {
        /** @var User $user */
        $user = Auth::user();

        // Ambil profil dari DB; bila belum ada tampilkan field kosong.
        $profile = $user->profile;

        return view('account.settings', [
            'user' => $user,
            'profile' => $profile,
            'profileComplete' => $user->hasCompleteProfile(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validated();

        // create-or-update berdasarkan pemilik profil (user login).
        $profile = EmployeeProfile::firstOrNew(['user_id' => $user->id]);
        $profile->fill([
            'full_name' => $validated['full_name'],
            'position' => $validated['position'],
            'work_unit' => $validated['work_unit'],
        ]);
        $profile->save();

        $this->auditService->log(
            AuditService::EVENT_PROFILE_UPDATED,
            $profile,
            $user->id,
        );

        return redirect()
            ->route('account.settings')
            ->with('status', 'Profil berhasil disimpan.');
    }
}
