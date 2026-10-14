<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DeclarationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check() && auth()->user()->isAdmin()) {
        return redirect()->route('admin.home');
    }

    if (auth()->check()) {
        return redirect()->route('home');
    }

    return redirect()->route('login');
});

Route::middleware(['auth', 'role:employee', 'no-cache'])->group(function () {
    Route::get('/home', function () {
        $user = auth()->user()->loadMissing('profile');
        $declaration = $user->latestDeclaration();

        return view('welcome', [
            'user' => $user,
            'declaration' => $declaration,
        ]);
    })->name('home');
});

/*
|--------------------------------------------------------------------------
| Registrasi akun (guest) — verifikasi OTP email
|--------------------------------------------------------------------------
| POST diberi throttle untuk membatasi penyalahgunaan (Req 1.4, 9.x).
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/register/send-otp', [RegisterController::class, 'sendOtp'])
            ->name('register.send-otp');
        Route::post('/register/verify-otp', [RegisterController::class, 'verifyOtp'])
            ->name('register.verify-otp');
        Route::post('/register', [RegisterController::class, 'store'])
            ->name('register.store');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(['auth', 'no-cache'])
    ->name('logout');

Route::middleware(['auth', 'no-cache'])->group(function () {
    Route::get('/account/settings', [ProfileController::class, 'edit'])
        ->name('account.settings');
    Route::put('/account/settings', [ProfileController::class, 'update'])
        ->name('account.update');
});

Route::middleware(['auth', 'role:employee', 'profile.complete', 'no-cache'])->group(function () {
    Route::get('/declarations/create', [DeclarationController::class, 'create'])
        ->name('declarations.create');

    Route::get('/declarations/{declaration}/edit', [DeclarationController::class, 'edit'])
        ->name('declarations.edit');

    Route::post('/declarations', [DeclarationController::class, 'store'])
        ->name('declarations.store');

    Route::put('/declarations/{declaration}', [DeclarationController::class, 'update'])
        ->name('declarations.update');
});

Route::middleware(['auth', 'no-cache'])->group(function () {
    Route::get('/declarations/{declaration}/preview', [DeclarationController::class, 'preview'])
        ->name('declarations.preview');

    Route::get('/declarations/{declaration}/pdf', [DeclarationController::class, 'pdf'])
        ->name('declarations.pdf');

    Route::get('/declarations/{declaration}', [DeclarationController::class, 'show'])
        ->name('declarations.show');
});

Route::middleware(['auth', 'role:admin', 'no-cache'])->group(function () {
    Route::get('/admin', function () {
        return view('admin.home');
    })->name('admin.home');

    Route::get('/admin/data', [AdminUserController::class, 'declarationsIndex'])
        ->name('admin.data.index');

    Route::get('/admin/data/export', [AdminUserController::class, 'exportBundle'])
        ->name('admin.data.export');

    Route::get('/admin/users', [AdminUserController::class, 'index'])
        ->name('admin.users.index');

    Route::put('/admin/users/{user}/status', [AdminUserController::class, 'updateStatus'])
        ->name('admin.users.status');
});
