@extends('layouts.app')

@section('title', 'Isi Form Deklarasi')

@section('content')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css">
    <style>
        .quill-editor {
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            overflow: hidden;
            background: #fff;
        }

        .quill-editor .ql-toolbar {
            border: 0;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .quill-editor .ql-container {
            border: 0;
            min-height: 110px;
            font-size: 0.875rem;
        }

        .quill-editor .ql-editor {
            min-height: 110px;
            line-height: 1.5;
        }

        .quill-editor .ql-editor.ql-blank::before {
            color: #9ca3af;
            font-style: normal;
        }

        /* Modal konfirmasi simpan (gaya sendiri, tidak bergantung build Tailwind) */
        .cf-backdrop {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(17, 24, 39, .55);
        }

        .cf-dialog {
            width: 100%;
            max-width: 28rem;
            background: #fff;
            border-radius: .75rem;
            padding: 1.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, .25);
        }

        .cf-head {
            display: flex;
            gap: .75rem;
            align-items: flex-start;
        }

        .cf-icon {
            flex: none;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .cf-icon--ask {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .cf-icon--warn {
            background: #fef3c7;
            color: #b45309;
        }

        .cf-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 600;
            color: #111827;
        }

        .cf-text {
            margin: .35rem 0 0;
            font-size: .875rem;
            line-height: 1.5;
            color: #4b5563;
        }

        .cf-lock {
            margin-top: 1rem;
            padding: .75rem .9rem;
            border: 1px solid #fde68a;
            border-radius: .5rem;
            background: #fffbeb;
            color: #92400e;
            font-size: .875rem;
            line-height: 1.45;
        }

        .cf-summary {
            margin: 1rem 0 0;
            padding: .6rem .9rem;
            border-radius: .5rem;
            background: #f3f4f6;
            font-size: .8125rem;
            color: #374151;
        }

        .cf-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .5rem;
            margin-top: 1.25rem;
        }

        .cf-btn {
            border-radius: .5rem;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            padding: .55rem 1rem;
            font-size: .875rem;
            font-weight: 500;
            cursor: pointer;
        }

        .cf-btn:hover {
            background: #f9fafb;
        }

        .cf-btn--primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }

        .cf-btn--primary:hover {
            background: #1d4ed8;
        }

        .cf-btn--danger {
            background: #b45309;
            border-color: #b45309;
            color: #fff;
        }

        .cf-btn--danger:hover {
            background: #92400e;
        }

        @media (max-width: 480px) {
            .cf-actions .cf-btn {
                flex: 1 1 100%;
            }
        }
    </style>

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
        isAdmin: @js((bool) auth()->user()?->isAdmin()),
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
            class="space-y-6" x-ref="form" @submit.prevent="askConfirm()">
            @csrf
            @if ($declaration)
                @method('PUT')
            @endif

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-base font-semibold text-gray-900">A. Data Karyawan</h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ $declaration
                        ? 'Data berikut diambil dari profil karyawan pemilik deklarasi ini dan tidak dapat diubah di sini.'
                        : 'Data berikut diambil dari profil akun Anda dan tidak dapat diubah di sini.' }}
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
                        <dt class="text-sm font-medium text-gray-700">NIP/NIK</dt>
                        <dd data-field="employee_nik"
                            class="mt-1 rounded-md border border-gray-200 bg-gray-100 px-3 py-2.5 text-base text-gray-800">
                            {{ $employee['nip'] ?? '-' }} / {{ $employee['nik'] ?? '-' }}
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
                            Satu rincian muncul otomatis untuk setiap kategori yang Anda pilih. Lengkapi semuanya.
                        </p>
                    </div>
                </div>

                @error('details')
                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                @enderror


                <div class="mt-5 space-y-4" x-show="details.length > 0">
                    <template x-for="(detail, index) in details" :key="detail._uid">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 shadow-sm">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700"
                                        x-text="index + 1"></span>
                                    <span class="text-sm font-semibold text-gray-700"
                                        x-text="categoryLabel(detail.category_key)"></span>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">Kondisi</label>
                                    <input type="hidden" :name="`details[${index}][category_key]`"
                                        :value="detail.category_key">
                                    <p class="block w-full rounded-md border border-gray-200 bg-gray-100 px-2 py-2 text-sm text-gray-800"
                                        x-text="categoryLabel(detail.category_key)"></p>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">Nama Pihak Terkait</label>
                                    <input type="text" :name="`details[${index}][related_party_name]`"
                                        x-model="detail.related_party_name"
                                        class="block w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">Penjelasan Situasi</label>
                                    <textarea :name="`details[${index}][situation_explanation]`" x-model="detail.situation_explanation" class="hidden"></textarea>
                                    <div class="quill-editor" x-init="initQuill($el, $el.previousElementSibling, detail)"></div>
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

        {{-- Modal konfirmasi sebelum menyimpan --}}
        <div class="cf-backdrop" x-show="confirming" x-cloak x-transition.opacity @click.self="confirming = false"
            @keydown.escape.window="confirming = false"
            x-effect="document.body.style.overflow = confirming ? 'hidden' : ''">
            <div class="cf-dialog" role="alertdialog" aria-modal="true" aria-labelledby="cf-title"
                aria-describedby="cf-desc">
                <div class="cf-head">
                    <div class="cf-icon" :class="isAdmin ? 'cf-icon--ask' : 'cf-icon--warn'" aria-hidden="true"
                        x-text="isAdmin ? '?' : '!'"></div>
                    <div>
                        <h2 id="cf-title" class="cf-title"
                            x-text="isAdmin ? 'Simpan jawaban deklarasi?' : 'Simpan deklarasi Anda?'"></h2>
                        <p id="cf-desc" class="cf-text">
                            <template x-if="isAdmin">
                                <span>Apakah Anda sudah yakin dengan jawaban yang diisi? Periksa kembali sebelum
                                    menyimpan.</span>
                            </template>
                            <template x-if="!isAdmin">
                                <span>Periksa kembali seluruh jawaban Anda sebelum menyimpan.</span>
                            </template>
                        </p>
                    </div>
                </div>

                <p class="cf-summary" x-text="summary()"></p>

                <div class="cf-lock" x-show="!isAdmin">
                    <strong>Perhatian:</strong> setelah disimpan, jawaban deklarasi
                    <strong>tidak dapat diubah lagi</strong>. Pastikan semua data sudah benar.
                </div>

                <div class="cf-actions">
                    <button type="button" class="cf-btn" x-ref="cancelBtn" @click="confirming = false">
                        Periksa lagi
                    </button>
                    <button type="button" class="cf-btn" :class="isAdmin ? 'cf-btn--primary' : 'cf-btn--danger'"
                        @click="confirmSubmit()" x-text="isAdmin ? 'Ya, simpan' : 'Ya, simpan permanen'"></button>
                </div>
            </div>
        </div>
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
                confirming: false,
                isAdmin: !!initial.isAdmin,
                _uidCounter: 0,

                init() {
                    const old = initial.details && typeof initial.details === 'object' ?
                        Object.values(initial.details) : [];
                    this.details = old.map((d) => ({
                        _uid: this._uidCounter++,
                        category_key: d.category_key ?? '',
                        related_party_name: d.related_party_name ?? '',
                        situation_explanation: d.situation_explanation ?? '',
                    }));
                    this.syncDetails();
                    this.$watch('categories', () => this.syncDetails());
                },

                summary() {
                    if (this.status === 'none') {
                        return 'Status: Tidak memiliki benturan kepentingan.';
                    }
                    return 'Status: Memiliki benturan kepentingan \u2014 ' + this.details.length + ' rincian.';
                },

                askConfirm() {
                    if (this.submitting) return;
                    // Belum memilih status: kirim saja agar pesan validasi server muncul
                    if (!this.status) {
                        this.submitting = true;
                        this.$refs.form.submit();
                        return;
                    }
                    this.confirming = true;
                    this.$nextTick(() => this.$refs.cancelBtn && this.$refs.cancelBtn.focus());
                },

                confirmSubmit() {
                    this.confirming = false;
                    this.submitting = true;
                    this.$refs.form.submit();
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
                    } else if (value === 'has_conflict') {
                        this.syncDetails();
                    }
                },

                // Satu rincian per kategori terpilih, selalu mengikuti urutan kategori di Bagian B.
                // Rincian yang sudah ada (beserta isiannya) dipertahankan; yang kategorinya dicabut dibuang.
                syncDetails() {
                    const next = [];
                    this.categoryKeys.forEach((key) => {
                        if (!this.categories.includes(key)) return;
                        const existing = this.details.filter((d) => d.category_key === key);
                        if (existing.length > 0) {
                            next.push(...existing);
                        } else {
                            next.push({
                                _uid: this._uidCounter++,
                                category_key: key,
                                related_party_name: '',
                                situation_explanation: '',
                            });
                        }
                    });
                    const same = next.length === this.details.length && next.every((d, i) => d === this.details[i]);
                    if (!same) this.details = next;
                },

                onCategoryToggle(key, checked) {
                    if (checked) return; // rincian dibuat otomatis oleh syncDetails()

                    const filled = this.details.some((d) =>
                        d.category_key === key &&
                        ((d.related_party_name || '').trim() !== '' || (d.situation_explanation || '').trim() !== '')
                    );
                    if (filled) {
                        const ok = window.confirm(
                            'Rincian untuk kategori ini sudah diisi. Menghapus centang akan menghapus rincian tersebut. Lanjutkan?'
                        );
                        if (!ok) {
                            if (!this.categories.includes(key)) {
                                this.categories.push(key);
                            }
                            return;
                        }
                    }
                    this.categories = this.categories.filter((k) => k !== key);
                    this.details = this.details.filter((d) => d.category_key !== key);
                },

                initQuill(editorElement, hiddenField, detail) {
                    if (!hiddenField) {
                        return;
                    }

                    if (typeof Quill === 'undefined') {
                        hiddenField.classList.remove('hidden');
                        return;
                    }

                    const editor = new Quill(editorElement, {
                        theme: 'snow',
                        modules: {
                            toolbar: [
                                ['bold', 'italic', 'underline'],
                                [{
                                    list: 'ordered'
                                }, {
                                    list: 'bullet'
                                }],
                                ['clean'],
                            ],
                        },
                    });

                    const initialHtml = detail.situation_explanation || '';
                    if (initialHtml.trim() !== '') {
                        editor.clipboard.dangerouslyPasteHTML(initialHtml);
                    } else {
                        editor.root.innerHTML = '<p><br></p>';
                    }

                    editor.on('text-change', () => {
                        const html = editor.root.innerHTML.trim() === '<p><br></p>' ? '' : editor.root.innerHTML;
                        hiddenField.value = html;
                        detail.situation_explanation = html;
                    });
                },
            };
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.min.js"></script>
@endsection
