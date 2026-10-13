<?php

namespace App\Http\Requests;

use App\Models\ConflictDeclaration;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi penyimpanan deklarasi benturan kepentingan — Bagian B/C (Requirement 4).
 *
 * Form Request ini menegakkan konsistensi lintas-field antara status (Bagian B)
 * dengan kategori dan rincian (Bagian C) SECARA OTORITATIF di server. Validasi
 * TIDAK bergantung pada JavaScript (Req 4.5 & 4.9): seluruh aturan dicek ulang di
 * sini meskipun UI Alpine sudah membatasi input.
 *
 * Field form (lihat resources/views/declarations/create.blade.php):
 *   - declaration_status: 'none' | 'has_conflict'
 *   - categories[]:       daftar key kategori yang dicentang
 *   - details[i][category_key|related_party_name|situation_explanation]
 *
 * Requirements: 4.4, 4.5, 4.9, 4.10
 */
class StoreDeclarationRequest extends FormRequest
{
    /**
     * Otorisasi ditangani oleh middleware rute (`auth` + `profile.complete`).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Daftar key kategori yang valid, diambil dari config aplikasi.
     *
     * @return array<int, string>
     */
    protected function validCategoryKeys(): array
    {
        return array_keys(config('conflict_categories', []));
    }

    /**
     * Aturan validasi dasar (struktur & tipe field).
     *
     * Konsistensi lintas-field (status ↔ kategori ↔ rincian) ditegakkan pada
     * {@see withValidator()} karena bergantung pada beberapa field sekaligus.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $validKeys = $this->validCategoryKeys();

        return [
            'declaration_status' => [
                'required',
                Rule::in([
                    ConflictDeclaration::STATUS_NONE,
                    ConflictDeclaration::STATUS_HAS_CONFLICT,
                ]),
            ],

            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', Rule::in($validKeys), 'distinct'],

            'details' => ['nullable', 'array'],
            'details.*.category_key' => ['nullable', 'string'],
            'details.*.related_party_name' => ['nullable', 'string', 'max:255'],
            'details.*.situation_explanation' => ['nullable', 'string'],
        ];
    }

    /**
     * Konsistensi lintas-field Bagian B ↔ Bagian C.
     *
     * - Status `none`  → kategori & rincian WAJIB kosong (Req 4.5).
     * - Status `has_conflict`:
     *     * minimal 1 kategori dipilih (Req 4.4);
     *     * minimal 1 rincian diisi (Req 4.10);
     *     * tiap rincian: category_key non-kosong & merupakan subset kategori
     *       terpilih (Req 4.9), nama pihak terkait & penjelasan non-kosong (Req 4.10).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Jika aturan dasar sudah gagal (mis. status tidak valid), lewati
            // pengecekan lintas-field agar pesan tidak membingungkan.
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $status = $this->input('declaration_status');
            $categories = $this->normalizedCategories();
            $details = $this->input('details', []);
            $details = is_array($details) ? $details : [];

            if ($status === ConflictDeclaration::STATUS_NONE) {
                $this->validateNone($validator, $categories, $details);

                return;
            }

            if ($status === ConflictDeclaration::STATUS_HAS_CONFLICT) {
                $this->validateHasConflict($validator, $categories, $details);
            }
        });
    }

    /**
     * Aturan untuk status `none` (Tidak memiliki benturan) — Req 4.5.
     *
     * @param  array<int, string>  $categories
     * @param  array<int, mixed>  $details
     */
    protected function validateNone(Validator $validator, array $categories, array $details): void
    {
        if (! empty($categories)) {
            $validator->errors()->add(
                'categories',
                'Status "Tidak memiliki" tidak boleh disertai kategori yang dipilih.'
            );
        }

        if ($this->hasAnyDetailContent($details)) {
            $validator->errors()->add(
                'details',
                'Status "Tidak memiliki" tidak boleh disertai rincian Bagian C.'
            );
        }
    }

    /**
     * Aturan untuk status `has_conflict` (Memiliki benturan) — Req 4.4, 4.9, 4.10.
     *
     * @param  array<int, string>  $categories
     * @param  array<int, mixed>  $details
     */
    protected function validateHasConflict(Validator $validator, array $categories, array $details): void
    {
        // Req 4.4 — minimal satu kategori wajib dipilih.
        if (empty($categories)) {
            $validator->errors()->add(
                'categories',
                'Pilih minimal satu kategori benturan kepentingan.'
            );
        }

        // Req 4.10 — minimal satu rincian wajib diisi.
        if (! $this->hasAnyDetailContent($details)) {
            $validator->errors()->add(
                'details',
                'Isi minimal satu rincian pada Bagian C.'
            );

            return;
        }

        // Req 4.9 & 4.10 — validasi tiap baris rincian.
        foreach ($details as $index => $detail) {
            $detail = is_array($detail) ? $detail : [];

            $categoryKey = trim((string) ($detail['category_key'] ?? ''));
            $partyName = trim((string) ($detail['related_party_name'] ?? ''));
            $explanation = trim((string) ($detail['situation_explanation'] ?? ''));

            // Kategori rincian wajib diisi dan harus merupakan subset kategori
            // yang dicentang di Bagian B (Req 4.9).
            if ($categoryKey === '') {
                $validator->errors()->add(
                    "details.{$index}.category_key",
                    'Kategori rincian wajib dipilih.'
                );
            } elseif (! in_array($categoryKey, $categories, true)) {
                $validator->errors()->add(
                    "details.{$index}.category_key",
                    'Kategori rincian harus termasuk kategori yang dicentang pada Bagian B.'
                );
            }

            // Req 4.10 — nama pihak terkait wajib diisi.
            if ($partyName === '') {
                $validator->errors()->add(
                    "details.{$index}.related_party_name",
                    'Nama Pihak Terkait wajib diisi.'
                );
            }

            // Req 4.10 — penjelasan situasi wajib diisi.
            if ($explanation === '') {
                $validator->errors()->add(
                    "details.{$index}.situation_explanation",
                    'Penjelasan Situasi wajib diisi.'
                );
            }
        }
    }

    /**
     * Kategori yang dinormalkan menjadi array string tanpa nilai kosong.
     *
     * @return array<int, string>
     */
    protected function normalizedCategories(): array
    {
        $categories = $this->input('categories', []);
        $categories = is_array($categories) ? $categories : [];

        return array_values(array_filter(
            array_map(static fn ($value): string => trim((string) $value), $categories),
            static fn (string $value): bool => $value !== ''
        ));
    }

    /**
     * Apakah ada minimal satu baris rincian yang mengandung isi (field non-kosong)?
     *
     * Baris yang seluruh fieldnya kosong dianggap "tidak ada" sehingga tombol
     * Tambah baris kosong pada UI tidak dianggap sebagai rincian.
     *
     * @param  array<int, mixed>  $details
     */
    protected function hasAnyDetailContent(array $details): bool
    {
        foreach ($details as $detail) {
            $detail = is_array($detail) ? $detail : [];

            $categoryKey = trim((string) ($detail['category_key'] ?? ''));
            $partyName = trim((string) ($detail['related_party_name'] ?? ''));
            $explanation = trim((string) ($detail['situation_explanation'] ?? ''));

            if ($categoryKey !== '' || $partyName !== '' || $explanation !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Data terstandardisasi untuk konsumsi service (Task 6.3, opsional).
     *
     * Mengembalikan status, kategori yang sudah dinormalkan, dan rincian yang
     * sudah dibersihkan (trim). Untuk status `none`, kategori & rincian selalu
     * kosong agar konsisten dengan aturan di atas.
     *
     * @return array{
     *     declaration_status: string,
     *     categories: array<int, string>,
     *     details: array<int, array{category_key: string, related_party_name: string, situation_explanation: string}>
     * }
     */
    public function normalized(): array
    {
        $status = (string) $this->validated()['declaration_status'];

        if ($status === ConflictDeclaration::STATUS_NONE) {
            return [
                'declaration_status' => $status,
                'categories' => [],
                'details' => [],
            ];
        }

        $details = $this->input('details', []);
        $details = is_array($details) ? $details : [];

        $normalizedDetails = [];
        foreach ($details as $detail) {
            $detail = is_array($detail) ? $detail : [];

            $categoryKey = trim((string) ($detail['category_key'] ?? ''));
            $partyName = trim((string) ($detail['related_party_name'] ?? ''));
            $explanation = trim((string) ($detail['situation_explanation'] ?? ''));

            if ($categoryKey === '' && $partyName === '' && $explanation === '') {
                continue;
            }

            $normalizedDetails[] = [
                'category_key' => $categoryKey,
                'related_party_name' => $partyName,
                'situation_explanation' => $explanation,
            ];
        }

        return [
            'declaration_status' => $status,
            'categories' => $this->normalizedCategories(),
            'details' => $normalizedDetails,
        ];
    }

    /**
     * Nama atribut untuk pesan validasi (Bahasa Indonesia).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'declaration_status' => 'Status benturan kepentingan',
            'categories' => 'Kategori benturan kepentingan',
            'categories.*' => 'Kategori',
            'details' => 'Rincian Bagian C',
        ];
    }

    /**
     * Pesan kesalahan dasar dalam Bahasa Indonesia (Req 10.4).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'declaration_status.required' => 'Status benturan kepentingan wajib dipilih.',
            'declaration_status.in' => 'Status benturan kepentingan tidak valid.',
            'categories.array' => 'Format kategori tidak valid.',
            'categories.*.in' => 'Kategori yang dipilih tidak dikenal.',
            'categories.*.distinct' => 'Kategori tidak boleh duplikat.',
            'categories.*.string' => 'Kategori tidak valid.',
            'details.array' => 'Format rincian tidak valid.',
            'details.*.related_party_name.max' => 'Nama Pihak Terkait maksimal 255 karakter.',
        ];
    }
}
