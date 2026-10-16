@extends('layouts.app')

@section('title', 'Data Deklarasi')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Data Deklarasi</h1>
        <p class="mt-1 text-sm text-gray-600">
            Daftar karyawan yang sudah mengisi form deklarasi benturan kepentingan.
        </p>
    </div>

    @if (session('status'))
        <div role="alert" class="mb-5 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            {{ session('status') }}
        </div>
    @endif

    @php
        $hasFilter = filled($search) || filled($status);
    @endphp

    {{-- Gaya khusus bar filter: tidak bergantung pada kelas Tailwind yang harus dikompilasi ulang --}}
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
        <div class="dd-top">
            <form method="GET" action="{{ route('admin.data.index') }}" class="dd-filter">
                <div class="dd-field dd-field--grow">
                    <label for="q">Cari</label>
                    <div class="dd-search">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                        </svg>
                        <input type="search" id="q" name="q" value="{{ $search }}"
                            placeholder="Nama, NIK, NIP, atau email" autocomplete="off" class="dd-input">
                    </div>
                </div>

                <div class="dd-field">
                    <label for="status">Status</label>
                    <select id="status" name="status" onchange="this.form.submit()" class="dd-select">
                        <option value="">Semua status</option>
                        <option value="has_conflict" @selected($status === 'has_conflict')>Memiliki benturan</option>
                        <option value="none" @selected($status === 'none')>Tidak memiliki</option>
                    </select>
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
                        <a href="{{ route('admin.data.index', ['per_page' => $perPage]) }}" class="dd-btn">Reset</a>
                    @endif
                </div>
            </form>

            <div class="dd-exports">
                {{-- Excel: seluruh pengguna (kecuali admin), termasuk yang belum mengisi --}}
                <a href="{{ route('admin.data.export-excel') }}" class="dd-btn dd-btn--green">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.375 19.5h17.25M3.375 4.5h17.25v15H3.375v-15Zm0 5h17.25M3.375 14.5h17.25M9.125 4.5v15" />
                    </svg>
                    Export Excel
                </a>

                {{-- Bundle PDF: hanya data yang sedang tampil di halaman ini --}}
                <form method="GET" action="{{ route('admin.data.export') }}" class="dd-bundle">
                    @foreach ($declarations as $row)
                        <input type="hidden" name="ids[]" value="{{ $row->id }}">
                    @endforeach
                    <button type="submit" class="dd-btn dd-btn--green" @disabled($declarations->isEmpty())>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 3.75v9m0 0 3.75-3.75M12 12.75 8.25 9M4.5 18.75a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25" />
                        </svg>
                        Download bundle ({{ $declarations->count() }})
                    </button>
                </form>
            </div>
        </div>

        <p class="dd-count">
            @if ($declarations->total() > 0)
                Menampilkan {{ $declarations->firstItem() }}-{{ $declarations->lastItem() }} dari
                {{ $declarations->total() }} data
                @if ($hasFilter)
                    <span style="color:#6b7280">(hasil filter)</span>
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
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">NIK</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">NIP</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Tanggal</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($declarations as $declaration)
                    <tr>
                        <td class="px-4 py-3 text-gray-600">{{ $declarations->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3 text-gray-900">{{ $declaration->employee_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $declaration->employee_nik ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $declaration->employee_nip ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @if ($declaration->declaration_status === \App\Models\ConflictDeclaration::STATUS_HAS_CONFLICT)
                                <span
                                    class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-200">Memiliki
                                    benturan</span>
                            @else
                                <span
                                    class="inline-flex rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-green-200">Tidak
                                    memiliki</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $declaration->submitted_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '-' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('declarations.show', $declaration) }}"
                                    aria-label="Lihat detail deklarasi" title="Lihat detail"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-blue-200 bg-white text-blue-700 transition hover:bg-blue-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Zm9.75 3.75A3.75 3.75 0 1 0 12 8.25a3.75 3.75 0 0 0 0 7.5Z" />
                                    </svg>
                                </a>
                                <a href="{{ route('declarations.pdf', $declaration) }}" aria-label="Unduh PDF deklarasi"
                                    title="Unduh PDF"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-700 transition hover:bg-gray-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 3.75v9m0 0 3.75-3.75M12 12.75 8.25 9M4.5 18.75a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25" />
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                            @if ($hasFilter)
                                Tidak ada data yang cocok dengan pencarian atau filter.
                            @else
                                Belum ada data deklarasi yang diisi.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $declarations->appends(array_filter(['q' => $search, 'status' => $status, 'per_page' => $perPage], fn($v) => filled($v)))->links() }}
    </div>
@endsection
