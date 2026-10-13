<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Alias middleware kustom (Laravel 11).
        // profile.complete: menjaga route form deklarasi agar hanya dapat
        // diakses user dengan profil lengkap (Req 3.4).
        // role: membatasi akses berdasarkan role, mis. role:admin (Req 8.3).
        // no-cache: mencegah browser cache halaman sensitif serta back-button
        // menampilkan halaman yang sudah logout (Req keamanan sesi browser).
        $middleware->alias([
            'profile.complete' => \App\Http\Middleware\EnsureProfileComplete::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'no-cache' => \App\Http\Middleware\NoCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
