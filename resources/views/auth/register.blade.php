@extends('layouts.app')

@section('title', 'Registrasi Akun')

@section('content')
<div class="mx-auto w-full max-w-md" x-data="registerForm()">
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
        <header class="mb-6 text-center">
            <h1 class="text-xl font-semibold text-gray-900">Registrasi Akun</h1>
            <p class="mt-1 text-sm text-gray-500">
                Gunakan email perusahaan Anda yang berakhiran <span class="font-medium">@sig.id</span>.
            </p>
        </header>

        {{-- Indikator langkah --}}
        <ol class="mb-6 flex items-center justify-between text-xs font-medium text-gray-400">
            <li :class="step >= 1 ? 'text-blue-700' : ''">1. Email</li>
            <li :class="step >= 2 ? 'text-blue-700' : ''">2. Verifikasi</li>
            <li :class="step >= 3 ? 'text-blue-700' : ''">3. Data Akun</li>
        </ol>

        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <template x-if="notice">
            <div class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700"
                 role="status" x-text="notice"></div>
        </template>

        <section x-show="step === 1" x-cloak>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input id="email" type="email" x-model="email" autocomplete="email"
                   @keydown.enter.prevent="sendOtp()"
                   class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                   placeholder="nama@sig.id">
            <p class="mt-1 text-sm text-red-600" x-show="emailError" x-text="emailError" x-cloak></p>

            <button type="button" @click="sendOtp()" :disabled="loading"
                    class="mt-5 flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-60">
                <span x-show="!loading">Kirim OTP</span>
                <span x-show="loading" x-cloak>Memproses...</span>
            </button>
        </section>

        <section x-show="step === 2" x-cloak>
            <p class="mb-4 text-sm text-gray-600">
                Masukkan 6 digit kode yang dikirim ke <span class="font-medium" x-text="email"></span>.
            </p>

            <label for="code" class="block text-sm font-medium text-gray-700">Kode OTP</label>
            <input id="code" type="text" inputmode="numeric" maxlength="6" x-model="code"
                   @keydown.enter.prevent="verifyOtp()"
                   class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-center text-lg tracking-[0.5em] shadow-sm focus:border-blue-500 focus:ring-blue-500"
                   placeholder="______">
            <p class="mt-1 text-sm text-red-600" x-show="codeError" x-text="codeError" x-cloak></p>

            <button type="button" @click="verifyOtp()" :disabled="loading"
                    class="mt-5 flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-60">
                <span x-show="!loading">Verifikasi</span>
                <span x-show="loading" x-cloak>Memproses...</span>
            </button>

            <div class="mt-4 flex items-center justify-between text-sm">
                <button type="button" @click="step = 1" class="text-gray-500 hover:text-gray-700">
                    &larr; Ubah email
                </button>
                <button type="button" @click="sendOtp()" :disabled="cooldown > 0 || loading"
                        class="font-medium text-blue-700 hover:text-blue-800 disabled:text-gray-400">
                    <span x-show="cooldown === 0">Kirim ulang kode</span>
                    <span x-show="cooldown > 0" x-cloak>Kirim ulang dalam <span x-text="cooldown"></span>s</span>
                </button>
            </div>
        </section>

        <section x-show="step === 3" x-cloak>
            <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="email" :value="email">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" :value="email" readonly
                           class="mt-1 block w-full cursor-not-allowed rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                </div>

                <div>
                    <label for="nik" class="block text-sm font-medium text-gray-700">NIK</label>
                    <input id="nik" name="nik" type="text" inputmode="numeric" maxlength="16"
                           value="{{ old('nik') }}" required
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="16 digit angka">
                    @error('nik')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="Minimal 8 karakter, kombinasi huruf dan angka">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Konfirmasi Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <button type="submit"
                        class="flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Buat Akun
                </button>
            </form>
        </section>

        <p class="mt-6 text-center text-sm text-gray-500">
            Sudah punya akun?
            <a href="{{ url('/login') }}" class="font-medium text-blue-700 hover:text-blue-800">Masuk di sini</a>
        </p>
    </div>
</div>

<script>
    function registerForm() {
        return {
            step: 1,
            email: '',
            code: '',
            loading: false,
            notice: '',
            emailError: '',
            codeError: '',
            cooldown: 0,
            cooldownTimer: null,

            csrf() {
                return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            },

            startCooldown() {
                this.cooldown = 60;
                clearInterval(this.cooldownTimer);
                this.cooldownTimer = setInterval(() => {
                    if (this.cooldown > 0) {
                        this.cooldown--;
                    } else {
                        clearInterval(this.cooldownTimer);
                    }
                }, 1000);
            },

            async sendOtp() {
                this.emailError = '';
                this.notice = '';
                if (this.cooldown > 0 && this.step === 2) return;
                this.loading = true;

                try {
                    const res = await fetch('{{ route('register.send-otp') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                        },
                        body: JSON.stringify({ email: this.email }),
                    });
                    const data = await res.json().catch(() => ({}));

                    if (res.status === 422) {
                        this.emailError = (data.errors && data.errors.email && data.errors.email[0]) || 'Email tidak valid.';
                        return;
                    }

                    this.notice = data.message || '';
                    if (res.ok) {
                        if (this.step === 1) this.step = 2;
                        this.startCooldown();
                    }
                } catch (e) {
                    this.notice = 'Terjadi kesalahan. Silakan coba lagi.';
                } finally {
                    this.loading = false;
                }
            },

            async verifyOtp() {
                this.codeError = '';
                this.loading = true;

                try {
                    const res = await fetch('{{ route('register.verify-otp') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                        },
                        body: JSON.stringify({ email: this.email, code: this.code }),
                    });
                    const data = await res.json().catch(() => ({}));

                    if (res.status === 422) {
                        this.codeError = (data.errors && data.errors.code && data.errors.code[0]) || 'Kode tidak valid.';
                        return;
                    }

                    if (data.verified) {
                        this.notice = '';
                        this.step = 3;
                    } else {
                        this.codeError = data.message || 'Kode verifikasi tidak valid atau telah kedaluwarsa.';
                    }
                } catch (e) {
                    this.codeError = 'Terjadi kesalahan. Silakan coba lagi.';
                } finally {
                    this.loading = false;
                }
            },
        };
    }
</script>
@endsection
