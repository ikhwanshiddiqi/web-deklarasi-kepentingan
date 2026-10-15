@extends('layouts.app')

@section('title', 'Hasil Deklarasi')

@section('content')
    @php
        $isHasConflict = $declaration->declaration_status === \App\Models\ConflictDeclaration::STATUS_HAS_CONFLICT;
        $selectedCategories = $declaration->categories->pluck('category_key')->all();
    @endphp

    <div class="mx-auto w-full max-w-4xl">
        @if (session('status'))
            <div role="alert" class="mb-5 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Hasil Deklarasi</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Deklarasi #{{ $declaration->id }} &mdash; FI/SP/GRC/973 (Rev. 01).
                </p>
            </div>

            @if (auth()->check() && auth()->user()->role == 'admin')
                <a href="{{ route('declarations.edit', $declaration) }}"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                    Edit Jawaban
                </a>
            @endif
        </div>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Status Pengiriman</h2>
            <dl class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-gray-500">Status Deklarasi</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900"
                        data-declaration-status="{{ $declaration->declaration_status }}">
                        @if ($isHasConflict)
                            Memiliki potensi benturan kepentingan
                        @else
                            Tidak memiliki potensi benturan kepentingan
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Waktu Kirim</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900">
                        @if ($declaration->updated_at)
                            {{ $declaration->updated_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
                        @else
                            &mdash;
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Data Karyawan</h2>
            <dl class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-gray-500">Nama Lengkap</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $declaration->employee_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">NIK</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $declaration->employee_nik }}</dd>
                </div>
                {{-- <div>
                    <dt class="text-xs text-gray-500">Jabatan/Posisi</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $declaration->employee_position }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Unit Kerja</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $declaration->employee_work_unit }}</dd>
                </div> --}}
            </dl>
        </section>

        @if ($isHasConflict)
            <section class="mb-6 rounded-lg border border-gray-200 bg-white p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Ringkasan</h2>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full border-collapse text-left text-sm text-gray-900">
                        <thead>
                            <tr class="bg-slate-100 text-xs uppercase tracking-wide text-gray-700">
                                <th class="border border-gray-200 px-3 py-2">No.</th>
                                <th class="border border-gray-200 px-3 py-2">Kondisi yang dicentang</th>
                                <th class="border border-gray-200 px-3 py-2">Nama Pihak Terkait / Perusahaan terafiliasi
                                </th>
                                <th class="border border-gray-200 px-3 py-2">Penjelasan situasi / Hubungan terkait</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($declaration->details as $index => $detail)
                                @php
                                    $detailKey = $detail->category_key ?? '';
                                @endphp
                                <tr class="align-top">
                                    <td class="border border-gray-200 px-3 py-2 text-center">{{ $index + 1 }}</td>
                                    <td class="border border-gray-200 px-3 py-2">
                                        {{ $categories[$detailKey]['label'] ?? $detailKey }}
                                    </td>
                                    <td class="border border-gray-200 px-3 py-2">
                                        {{ $detail->related_party_name ?? '-' }}
                                    </td>
                                    <td class="border border-gray-200 px-3 py-2">
                                        {{-- RichText mengubah daftar Quill jadi <ol>/<ul> asli dan membuang HTML berbahaya --}}
                                        <div class="[&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5">
                                            {!! \App\Services\RichTextService::toHtml($detail->situation_explanation) ?: '-' !!}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="border border-gray-200 px-3 py-4 text-center text-gray-500">
                                        Belum ada rincian yang diisi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <p class="mt-4 text-sm text-gray-700">
                    Jumlah rincian: <span class="font-medium">{{ $declaration->details->count() }}</span>
                </p>
            </section>
        @endif

        <section class="mb-6 rounded-lg border border-green-200 bg-green-50 p-5">
            <p class="text-lg font-bold tracking-wide text-green-700" data-approved-label>
                {{ $declaration->approved_label }}
            </p>
            <p class="mt-1 text-sm text-green-800">{{ $declaration->employee_name }}</p>
        </section>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-5">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Preview PDF</h2>
                <a href="{{ route('declarations.pdf', $declaration) }}"
                    class="text-sm font-medium text-blue-700 transition hover:text-blue-800">
                    Buka PDF penuh
                </a>
            </div>

            <div class="overflow-hidden rounded-md border border-gray-200 bg-gray-50 p-3">
                <a href="{{ route('declarations.preview', $declaration) }}" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center justify-center rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100">
                    Preview PDF
                </a>
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('declarations.pdf', $declaration) }}" data-download-pdf
                class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                Unduh PDF
            </a>
        </div>
    </div>

@endsection
