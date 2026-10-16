@extends('layouts.app')

@section('title', 'Kelola Akun')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Kelola Akun</h1>
        <p class="mt-1 text-sm text-gray-600">
            Daftar akun pengguna. Admin dapat mengaktifkan atau menonaktifkan akun.
        </p>
    </div>

    @if (session('status'))
        <div role="alert" class="mb-5 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div role="alert" class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    @php
        $hasFilter = filled($search);
    @endphp

    <style>
        .dd-bar {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: .5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .dd-top {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: .75rem 1rem;
        }

        .dd-filter {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: .75rem;
            flex: 1 1 auto;
            min-width: 0;
        }

        .dd-field {
            display: flex;
            flex-direction: column;
            gap: .25rem;
        }

        .dd-field--grow {
            flex: 1 1 16rem;
            min-width: 12rem;
        }

        .dd-field label {
            font-size: .75rem;
            font-weight: 500;
            color: #4b5563;
        }

        .dd-input,
        .dd-select {
            height: 2.5rem;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: .5rem;
            background: #fff;
            padding: 0 .75rem;
            font-size: .875rem;
            color: #1f2937;
        }

        .dd-input:focus,
        .dd-select:focus {
            outline: 2px solid #bfdbfe;
            border-color: #3b82f6;
        }

        .dd-search {
            position: relative;
        }

        .dd-search svg {
            position: absolute;
            left: .75rem;
            top: 50%;
            width: 1rem;
            height: 1rem;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }

        .dd-search .dd-input {
            width: 100%;
            padding-left: 2.25rem;
        }

        .dd-actions {
            display: flex;
            gap: .5rem;
        }

        .dd-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            height: 2.5rem;
            box-sizing: border-box;
            padding: 0 1rem;
            border-radius: .5rem;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            font-size: .875rem;
            font-weight: 500;
            line-height: 1;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }

        .dd-btn:hover {
            background: #f9fafb;
        }

        .dd-btn--primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }

        .dd-btn--primary:hover {
            background: #1d4ed8;
        }

        .dd-btn--green {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #15803d;
        }

        .dd-btn--green:hover {
            background: #dcfce7;
        }

        .dd-btn[disabled] {
            opacity: .5;
            cursor: not-allowed;
        }

        .dd-exports {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .dd-btn svg {
            width: 1rem;
            height: 1rem;
        }

        .dd-count {
            margin: .75rem 0 0;
            padding-top: .75rem;
            border-top: 1px solid #f3f4f6;
            font-size: .875rem;
            color: #4b5563;
        }

        @media (max-width: 640px) {

            .dd-exports,
            .dd-exports .dd-btn,
            .dd-bundle {
                width: 100%;
            }
        }
    </style>

    <div class="dd-bar">
        <form method="GET" action="{{ route('admin.users.index') }}" class="dd-filter">
            <div class="dd-field dd-field--grow">
                <label for="q">Cari</label>
                <div class="dd-search">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                    </svg>
                    <input type="search" id="q" name="q" value="{{ $search }}"
                        placeholder="Nama, email, NIK, atau NIP" autocomplete="off" class="dd-input">
                </div>
            </div>

            <div class="dd-field">
                <label for="per_page">Tampil</label>
                <select id="per_page" name="per_page" onchange="this.form.submit()" class="dd-select">
                    @foreach ([10, 25, 50, 100] as $value)
                        <option value="{{ $value }}" @selected((int) $perPage === $value)>{{ $value }}</option>
                    @endforeach
                </select>
            </div>

            <div class="dd-actions">
                <button type="submit" class="dd-btn dd-btn--primary">Cari</button>
                @if ($hasFilter)
                    <a href="{{ route('admin.users.index', ['per_page' => $perPage]) }}" class="dd-btn">Reset</a>
                @endif
            </div>
        </form>

        <p class="dd-count">
            @if ($users->total() > 0)
                Menampilkan {{ $users->firstItem() }}-{{ $users->lastItem() }} dari
                {{ $users->total() }} data
                @if ($hasFilter)
                    <span style="color:#6b7280">(hasil pencarian)</span>
                @endif
            @else
                Tidak ada data
            @endif
        </p>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">No</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Nama</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Email</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">NIK</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">NIP</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Role</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-4 py-3 text-gray-600">{{ $users->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3 text-gray-900">{{ $user->profile?->full_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $user->nik }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $user->nip }}</td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ $user->role === 'admin' ? 'Admin' : 'Karyawan' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->is_active)
                                <span
                                    class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                    Aktif
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                    Nonaktif
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->id === auth()->id())
                                <span class="text-xs text-gray-400">Akun Anda</span>
                            @else
                                <form method="POST" action="{{ route('admin.users.status', $user) }}" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="is_active" value="{{ $user->is_active ? '0' : '1' }}">
                                    @if ($user->is_active)
                                        <button type="submit"
                                            class="rounded-md border border-red-200 bg-white px-3 py-1 text-xs font-medium text-red-700 transition hover:bg-red-50">
                                            Nonaktifkan
                                        </button>
                                    @else
                                        <button type="submit"
                                            class="rounded-md border border-blue-200 bg-white px-3 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-50">
                                            Aktifkan
                                        </button>
                                    @endif
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                            @if ($hasFilter)
                                Tidak ada akun yang cocok dengan pencarian.
                            @else
                                Belum ada akun.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->appends(array_filter(['q' => $search, 'per_page' => $perPage], fn($v) => filled($v)))->links() }}
    </div>
@endsection