@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
    <div class="flex min-h-[70vh] items-center justify-center">
        <div class="w-full max-w-md">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">

                <div class="mb-6 text-center">
                    <div class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-white">
                        <img src="..\pict\logosp.png" alt="logo" style="width: 70px; height: 80px;">
                    </div>
                    <p class="mt-2 text-xs font-medium uppercase tracking-wide text-gray-500">
                        Portal Internal GRC
                    </p>
                </div>

                <h1 class="mb-1 text-center text-lg font-semibold leading-snug text-gray-900">
                    Portal Pernyataan Potensi Benturan Kepentingan
                </h1>
                <p class="mb-6 text-center text-sm text-gray-600">
                    Masuk menggunakan email perusahaan atau NIP Anda.
                </p>

                <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4" x-data="{ submitting: false }"
                    @submit="submitting = true">
                    @csrf

                    <div>
                        <label for="identity" class="mb-1 block text-sm font-medium text-gray-700">
                            Email perusahaan atau NIP
                        </label>
                        <input id="identity" name="identity" type="text" value="{{ old('identity') }}"
                            autocomplete="username" inputmode="text" required autofocus
                            class="block w-full rounded-md border px-3 py-2.5 text-base text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('identity') border-red-500 @enderror"
                            @error('identity') aria-invalid="true" aria-describedby="identity-error" @enderror>
                        @error('identity')
                            <p id="identity-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-gray-700">
                            Password
                        </label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                            class="block w-full rounded-md border px-3 py-2.5 text-base text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('password') border-red-500 @enderror"
                            @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        @error('password')
                            <p id="password-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox" value="1"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <label for="remember" class="ml-2 text-sm text-gray-700">Ingat saya</label>
                    </div>

                    <button type="submit" x-bind:disabled="submitting"
                        class="flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 text-base font-medium text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
                        <span x-show="!submitting">Masuk</span>
                        <span x-show="submitting" x-cloak>Memproses…</span>
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-gray-600" hidden>
                    Belum punya akun?
                    <a href="{{ route('register') }}" class="font-medium text-blue-600 hover:text-blue-700">
                        Buat akun
                    </a>
                </p>
            </div>
        </div>
    </div>
@endsection
