@extends('layouts.app')

@section('title', 'Isi Form Deklarasi — Portal Pernyataan Potensi Benturan Kepentingan')

@section('content')
    <div class="mx-auto w-full max-w-4xl" x-data="declarationForm({
        status: @js(old('declaration_status', $declaration?->declaration_status ?? '')),
        categories: @js(old('categories', $declaration?->categories->pluck('category_key')->all() ?? [])),
        details: @js(
    old(
        'details',
        $declaration?->details
            ->map(
                fn($detail) => [
                    'category_key' => $detail->category_key,
                    'related_party_name' => $detail->related_party_name,
                    'situation_explanation' => $detail->situation_explanation,
                ],
            )
            ->all() ?? [],
    ),
),
        categoryKeys: @js(array_keys($categories)),
    })" x-cloak>
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Isi Form Deklarasi</h1>
            <p class="mt-1 text-sm text-gray-600">
                Lengkapi pernyataan potensi benturan kepentingan Anda sesuai formulir resmi
                (FI/SP/GRC/973 &mdash; Rev. 01).
            </p>
        </div>

        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-medium">Terdapat kesalahan pada isian Anda:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
            action="{{ $declaration ? route('declarations.update', $declaration) : route('declarations.store') }}"
            class="space-y-6" x-ref="form" @submit="submitting = true">
            @csrf
            @if ($declaration)
                @method('PUT')
            @endif

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-base font-semibold text-gray-900">A. Data Karyawan</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Data berikut diambil dari profil akun Anda dan tidak dapat diubah di sini.
                </p>

                <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-700">Nama Lengkap</dt>
                        <dd data-field="employee_name"
                            class="mt-1 rounded-md border border-gray-200 bg-gray-100 px-3 py-2.5 text-base text-gray-800">
                            {{ $employee['full_name'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-700">NIK</dt>
                        <dd data-field="employee_nik"
                            class="mt-1 rounded-md border border-gray-200 bg-gray-100 px-3 py-2.5 text-base text-gray-800">
                            {{ $employee['nik'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-700">Jabatan / Posisi</dt>
                        <dd data-field="employee_position"
                            class="mt-1 rounded-md border border-gray-200 bg-gray-100 px-3 py-2.5 text-base text-gray-800">
                            {{ $employee['position'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-700">Unit Kerja</dt>
                        <dd data-field="employee_work_unit"
                            class="mt-1 rounded-md border border-gray-200 bg-gray-100 px-3 py-2.5 text-base text-gray-800">
                            {{ $employee['work_unit'] }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-base font-semibold text-gray-900">B. Deklarasi Benturan Kepentingan</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Pilih salah satu status di bawah ini.
                </p>

                @error('declaration_status')
                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <fieldset class="mt-5 space-y-3">
                    <legend class="sr-only">Status benturan kepentingan</legend>

                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-300 p-4 transition hover:border-blue-400"
                        :class="status === 'none' ? 'border-blue-500 ring-1 ring-blue-500' : ''">
                        <input type="radio" name="declaration_status" value="none"
                            class="mt-0.5 h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500" x-model="status"
                            @change="onStatusChange('none')">
                        <span>
                            <span class="block text-sm font-medium text-gray-900">Tidak memiliki</span>
                            <span class="block text-sm text-gray-600">
                                Tidak memiliki kondisi yang berpotensi menimbulkan benturan kepentingan.
                            </span>
                        </span>
                    </label>

                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-300 p-4 transition hover:border-blue-400"
                        :class="status === 'has_conflict' ? 'border-blue-500 ring-1 ring-blue-500' : ''">
                        <input type="radio" name="declaration_status" value="has_conflict"
                            class="mt-0.5 h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500" x-model="status"
                            @change="onStatusChange('has_conflict')">
                        <span>
                            <span class="block text-sm font-medium text-gray-900">Memiliki</span>
                            <span class="block text-sm text-gray-600">
                                Memiliki kondisi yang berpotensi menimbulkan benturan kepentingan.
                            </span>
                        </span>
                    </label>
                </fieldset>

                <div class="mt-6" x-show="status === 'has_conflict'" x-transition.opacity>
                    <h3 class="text-sm font-semibold text-gray-900">Pilih kategori yang relevan</h3>
                    <p class="mt-1 text-sm text-gray-600">Centang minimal satu kategori.</p>

                    @error('categories')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-4 space-y-3">
                        @foreach ($categories as $key => $category)
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-4 transition hover:border-blue-400">
                                <input type="checkbox" name="categories[]" value="{{ $key }}"
                                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    x-model="categories"
                                    @change="onCategoryToggle('{{ $key }}', $event.target.checked)">
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">{{ $category['label'] }}</span>
                                    <span class="block text-sm text-gray-600">{{ $category['description'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8" x-show="hasCategories()"
                x-transition.opacity>
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">
                            C. Tabel Identifikasi dan Deklarasi
                        </h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Isi rincian untuk setiap kondisi yang relevan. Minimal satu rincian wajib diisi.
                        </p>
                    </div>
                    <button type="button" @click="addDetail()"
                        class="mt-3 inline-flex items-center justify-center gap-1.5 self-start rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:mt-0">
                        <span aria-hidden="true">+</span> Tambah Rincian
                    </button>
                </div>

                @error('details')
                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <p class="mt-5 rounded-md border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500"
                    x-show="details.length === 0">
                    Belum ada rincian. Klik <span class="font-medium">Tambah Rincian</span> untuk menambahkan.
                </p>

                <div class="mt-5 hidden overflow-x-auto sm:block" x-show="details.length > 0">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-600">
                                <th class="w-12 py-2 pr-3 font-medium">No.</th>
                                <th class="py-2 pr-3 font-medium">Kondisi</th>
                                <th class="py-2 pr-3 font-medium">Nama Pihak Terkait</th>
                                <th class="py-2 pr-3 font-medium">Penjelasan Situasi</th>
                                <th class="w-16 py-2 font-medium"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(detail, index) in details" :key="detail._uid">
                                <tr class="align-top">
                                    <td class="py-3 pr-3 text-gray-700" x-text="index + 1"></td>
                                    <td class="py-3 pr-3">
                                        <select :name="`details[${index}][category_key]`" x-model="detail.category_key"
                                            class="block w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">— Pilih kondisi —</option>
                                            <template x-for="key in categories" :key="key">
                                                <option :value="key" x-text="categoryLabel(key)"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="py-3 pr-3">
                                        <input type="text" :name="`details[${index}][related_party_name]`"
                                            x-model="detail.related_party_name"
                                            class="block w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </td>
                                    <td class="py-3 pr-3">
                                        <textarea rows="2" :name="`details[${index}][situation_explanation]`" x-model="detail.situation_explanation"
                                            class="block w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                    </td>
                                    <td class="py-3">
                                        <button type="button" @click="removeDetail(index)"
                                            class="inline-flex items-center rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500">
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 space-y-4 sm:hidden" x-show="details.length > 0">
                    <template x-for="(detail, index) in details" :key="detail._uid">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-700">
                                    Rincian <span x-text="index + 1"></span>
                                </span>
                                <button type="button" @click="removeDetail(index)"
                                    class="inline-flex items-center rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50">
                                    Hapus
                                </button>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">Kondisi</label>
                                    <select :name="`details[${index}][category_key]`" x-model="detail.category_key"
                                        class="block w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">— Pilih kondisi —</option>
                                        <template x-for="key in categories" :key="key">
                                            <option :value="key" x-text="categoryLabel(key)"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">Nama Pihak Terkait</label>
                                    <input type="text" :name="`details[${index}][related_party_name]`"
                                        x-model="detail.related_party_name"
                                        class="block w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">Penjelasan Situasi</label>
                                    <textarea rows="3" :name="`details[${index}][situation_explanation]`" x-model="detail.situation_explanation"
                                        class="block w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-base font-semibold text-gray-900">Pernyataan</h2>

                <p class="mt-3 text-sm leading-relaxed text-gray-700">
                    Saya menyatakan bahwa seluruh informasi ini adalah benar dan lengkap sesuai dengan
                    kondisi yang saya ketahui saat ini, untuk dapat dipergunakan sebagaimana mestinya.
                </p>

                <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-700">Tanggal dibuat</dt>
                        <dd class="mt-1 text-base text-gray-800">
                            {{ \Illuminate\Support\Carbon::now('Asia/Jakarta')->translatedFormat('d F Y') }}
                            <span class="text-xs text-gray-500">(WIB)</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-700">Status persetujuan sistem</dt>
                        <dd class="mt-1">
                            <div
                                class="inline-flex flex-col items-center rounded-md border border-green-300 bg-green-50 px-5 py-3">
                                <span class="text-lg font-bold tracking-wide text-green-700">APPROVED</span>
                                <span class="mt-0.5 text-sm text-green-800">{{ $employee['full_name'] }}</span>
                            </div>
                        </dd>
                    </div>
                </dl>
            </section>

            <div class="flex justify-stretch sm:justify-end">
                <button type="submit" :disabled="submitting"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-5 py-2.5 text-base font-medium text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                    <svg x-show="submitting" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"
                        aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span x-text="submitting ? 'Memproses…' : 'Simpan dan Buat PDF'">Simpan dan Buat PDF</span>
                </button>
            </div>
        </form>
    </div>

    <script>
        function declarationForm(initial) {
            return {
                status: initial.status || '',
                categories: Array.isArray(initial.categories) ? [...initial.categories] : [],
                categoryKeys: initial.categoryKeys || [],
                categoryLabels: @js(collect($categories)->map(fn($c) => $c['label'])),
                details: [],
                submitting: false,
                _uidCounter: 0,

                init() {
                    const old = initial.details && typeof initial.details === 'object' ?
                        Object.values(initial.details) :
                        [];
                    this.details = old.map((d) => ({
                        _uid: this._uidCounter++,
                        category_key: d.category_key ?? '',
                        related_party_name: d.related_party_name ?? '',
                        situation_explanation: d.situation_explanation ?? '',
                    }));
                },

                hasCategories() {
                    return this.status === 'has_conflict' && this.categories.length > 0;
                },

                categoryLabel(key) {
                    return this.categoryLabels[key] ?? key;
                },

                onStatusChange(value) {
                    if (value === 'none' && (this.categories.length > 0 || this.details.length > 0)) {
                        const ok = window.confirm(
                            'Mengubah ke "Tidak memiliki" akan menghapus seluruh kategori dan rincian Bagian C. Lanjutkan?'
                        );
                        if (!ok) {
                            this.status = 'has_conflict';
                            return;
                        }
                        this.categories = [];
                        this.details = [];
                    }
                },

                onCategoryToggle(key, checked) {
                    if (!checked) {
                        const related = this.details.filter((d) => d.category_key === key);
                        if (related.length > 0) {
                            const ok = window.confirm(
                                'Kategori ini memiliki rincian pada Bagian C. Menghapus centang akan menghapus rincian tersebut. Lanjutkan?'
                            );
                            if (!ok) {
                                if (!this.categories.includes(key)) {
                                    this.categories.push(key);
                                }
                                return;
                            }
                            this.details = this.details.filter((d) => d.category_key !== key);
                        }
                    }
                },

                addDetail() {
                    this.details.push({
                        _uid: this._uidCounter++,
                        category_key: this.categories.length === 1 ? this.categories[0] : '',
                        related_party_name: '',
                        situation_explanation: '',
                    });
                },

                removeDetail(index) {
                    this.details.splice(index, 1);
                },
            };
        }
    </script>
@endsection
