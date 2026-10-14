<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        $this->auditService->log(AuditService::EVENT_LOGGED_IN, $user, $user->id);

        return redirect()->to($this->postLoginRedirect($user->hasCompleteProfile()));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->flush();
        $request->session()->regenerateToken();

        return redirect()->to($this->loginUrl())
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
                'Pragma' => 'no-cache',
                'Expires' => 'Sun, 02 Jan 1990 00:00:00 GMT',
            ]);
    }

    private function postLoginRedirect(bool $profileComplete): string
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user !== null && $user->isAdmin()) {
            return route('admin.home');
        }

        if (!$profileComplete) {
            return route('account.settings');
        }

        return route('home');
    }

    private function loginUrl(): string
    {
        return Route::has('login') ? route('login') : url('/login');
    }
}
