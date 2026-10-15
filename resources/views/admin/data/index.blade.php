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

    <div
        class="mb-4 flex flex-col gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm text-gray-600">
            @if ($declarations->total() > 0)
                Menampilkan {{ $declarations->firstItem() }}-{{ $declarations->lastItem() }} dari
                {{ $declarations->total() }} data
            @else
                Tidak ada data
            @endif
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.data.export') }}"
                class="inline-flex items-center justify-center rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm font-medium text-green-700 transition hover:bg-green-100">
                Download bundle
            </a>

            <form method="GET" action="{{ route('admin.data.index') }}"
                class="flex items-center gap-2 text-sm text-gray-600">
                <label for="per_page" class="font-medium text-gray-700">Tampil</label>
                <select id="per_page" name="per_page" onchange="this.form.submit()"
                    class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    @foreach ([10, 25, 50, 100] as $value)
                        <option value="{{ $value }}" {{ (int) $perPage === $value ? 'selected' : '' }}>
                            {{ $value }}
                        </option>
                    @endforeach
                </select>
                <span>per halaman</span>
            </form>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">No</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Nama</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">NIK</th>
                    {{-- <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Jabatan</th> --}}
                    {{-- <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Unit Kerja</th> --}}
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
                        {{-- <td class="px-4 py-3 text-gray-700">{{ $declaration->employee_position ?? '-' }}</td> --}}
                        {{-- <td class="px-4 py-3 text-gray-700">{{ $declaration->employee_work_unit ?? '-' }}</td> --}}
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
                            Belum ada data deklarasi yang diisi.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $declarations->appends(['per_page' => $perPage])->links() }}
    </div>
@endsection
