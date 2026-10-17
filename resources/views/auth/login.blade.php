@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
    {{-- Gaya khusus tombol lihat password. Memakai CSS sendiri agar tampil benar
         tanpa menunggu build Tailwind. --}}
    <style>
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 2.75rem; }
        /* Sembunyikan tombol lihat/hapus bawaan Microsoft Edge agar tidak dobel
           dengan tombol mata buatan sendiri. */
        .pw-wrap input::-ms-reveal,
        .pw-wrap input::-ms-clear { display: none; }
        .pw-toggle {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.75rem;
            border: 0;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
            border-radius: 0 .375rem .375rem 0;
        }
        .pw-toggle:hover { color: #374151; }
        .pw-toggle:focus-visible { outline: 2px solid #3b82f6; outline-offset: -2px; }
        .pw-toggle svg { width: 1.25rem; height: 1.25rem; }
    </style>

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
                    Portal Pernyataan Benturan Kepentingan
                </h1>
                <p class="mb-6 text-center text-sm text-gray-600">
                    Masuk menggunakan email perusahaan atau NIP Anda.
                </p>

                <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4"
                    x-data="{ submitting: false, showPassword: false }" @submit="submitting = true">
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
                        <div class="pw-wrap">
                            <input id="password" name="password" x-bind:type="showPassword ? 'text' : 'password'"
                                type="password" autocomplete="current-password" required
                                class="block w-full rounded-md border px-3 py-2.5 text-base text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('password') border-red-500 @enderror"
                                @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>

                            <button type="button" class="pw-toggle" @click="showPassword = !showPassword"
                                x-bind:aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                x-bind:aria-pressed="showPassword.toString()"
                                x-bind:title="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                aria-label="Tampilkan password" title="Tampilkan password">
                                {{-- Mata terbuka: password sedang tersembunyi, klik untuk menampilkan --}}
                                <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                {{-- Mata dicoret: password sedang terlihat, klik untuk menyembunyikan --}}
                                <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
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