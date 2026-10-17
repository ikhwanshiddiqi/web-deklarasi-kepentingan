@extends('layouts.app')

@section('title', 'Setting Akun')

@section('content')
    <div class="mx-auto w-full max-w-2xl">
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Setting Akun</h1>
            <p class="mt-1 text-sm text-gray-600">
                Lengkapi profil Anda agar dapat mengisi form deklarasi.
            </p>
        </div>

        @if (session('status'))
            <div role="status"
                 class="mb-5 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-6 flex items-center justify-between gap-3">
                <span class="text-sm font-medium text-gray-700">Status profil</span>
                @if ($profileComplete)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 111.42-1.42l2.79 2.79 6.79-6.79a1 1 0 011.42 0z" clip-rule="evenodd" />
                        </svg>
                        Lengkap
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8.257 3.1c.765-1.36 2.72-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM10 7a1 1 0 00-1 1v2a1 1 0 102 0V8a1 1 0 00-1-1zm0 6a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                        </svg>
                        Belum lengkap
                    </span>
                @endif
            </div>

            <form method="POST" action="{{ route('account.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                {{-- Email (readonly dari akun, Req 3.1). --}}
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-gray-700">
                        Email perusahaan
                    </label>
                    <input
                        id="email"
                        type="email"
                        value="{{ $user->email }}"
                        readonly
                        disabled
                        class="block w-full cursor-not-allowed rounded-md border border-gray-200 bg-gray-100 px-3 py-2.5 text-base text-gray-600 shadow-sm"
                    >
                    <p class="mt-1 text-xs text-gray-500">Email tidak dapat diubah.</p>
                </div>

                <div>
                    <label for="nik" class="mb-1 block text-sm font-medium text-gray-700">
                        NIK
                    </label>
                    <input
                        id="nik"
                        type="text"
                        value="{{ $user->nik }}"
                        readonly
                        disabled
                        class="block w-full cursor-not-allowed rounded-md border border-gray-200 bg-gray-100 px-3 py-2.5 text-base text-gray-600 shadow-sm"
                    >
                    <p class="mt-1 text-xs text-gray-500">NIK tidak dapat diubah.</p>
                </div>

                <div>
                    <label for="full_name" class="mb-1 block text-sm font-medium text-gray-700">
                        Nama Lengkap
                    </label>
                    <input
                        id="full_name"
                        name="full_name"
                        type="text"
                        value="{{ old('full_name', $profile->full_name ?? '') }}"
                        required
                        class="block w-full rounded-md border border-gray-300 px-3 py-2.5 text-base text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('full_name') border-red-500 @enderror"
                        @error('full_name') aria-invalid="true" aria-describedby="full_name-error" @enderror
                    >
                    @error('full_name')
                        <p id="full_name-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="position" class="mb-1 block text-sm font-medium text-gray-700">
                        Jabatan/Posisi
                    </label>
                    <input
                        id="position"
                        name="position"
                        type="text"
                        value="{{ old('position', $profile->position ?? '') }}"
                        required
                        class="block w-full rounded-md border border-gray-300 px-3 py-2.5 text-base text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('position') border-red-500 @enderror"
                        @error('position') aria-invalid="true" aria-describedby="position-error" @enderror
                    >
                    @error('position')
                        <p id="position-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="work_unit" class="mb-1 block text-sm font-medium text-gray-700">
                        Unit Kerja
                    </label>
                    <input
                        id="work_unit"
                        name="work_unit"
                        type="text"
                        value="{{ old('work_unit', $profile->work_unit ?? '') }}"
                        required
                        class="block w-full rounded-md border border-gray-300 px-3 py-2.5 text-base text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('work_unit') border-red-500 @enderror"
                        @error('work_unit') aria-invalid="true" aria-describedby="work_unit-error" @enderror
                    >
                    @error('work_unit')
                        <p id="work_unit-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 text-base font-medium text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto"
                    >
                        Simpan Profil
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
